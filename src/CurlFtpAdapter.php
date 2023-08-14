<?php

declare(strict_types=1);

namespace VladimirYuldashev\Flysystem;

use DateTime;
use Generator;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\UnixVisibility\VisibilityConverter;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use RuntimeException;
use Throwable;

class CurlFtpAdapter implements FilesystemAdapter
{
    private const SYSTEM_TYPE_WINDOWS = 'windows';
    private const SYSTEM_TYPE_UNIX = 'unix';

    private ?Curl $connection = null;
    private PathPrefixer $prefixer;
    private VisibilityConverter $visibilityConverter;
    private ?bool $isPureFtpdServer = null;
    private ?bool $useRawListOptions;
    private ?string $systemType;
    private MimeTypeDetector $mimeTypeDetector;

    private ?string $rootDirectory = null;
    protected string $separator = '/';

    public function __construct(
        private CurlFtpConnectionOptions $connectionOptions,
        VisibilityConverter $visibilityConverter = null,
        MimeTypeDetector $mimeTypeDetector = null,
        private bool $detectMimeTypeUsingPath = false,
    ) {
        $this->systemType = $this->connectionOptions->systemType();
        $this->visibilityConverter = $visibilityConverter ?: new PortableVisibilityConverter();
        $this->mimeTypeDetector = $mimeTypeDetector ?: new FinfoMimeTypeDetector();
        $this->useRawListOptions = $connectionOptions->useRawListOptions();
    }

    /**
     * Disconnect FTP connection on destruct.
     */
    public function __destruct()
    {
        if ($this->connection !== null) {
            $this->connection = null;
        }
    }

    /**
     * Establish a connection.
     */
    private function connect(): void
    {
        $this->connection = new Curl();
        $this->connection->setOptions([
            CURLOPT_URL => $this->connectionOptions->baseUrl(),
            CURLOPT_USERPWD => $this->connectionOptions->username() . ':' . $this->connectionOptions->password(),
            CURLOPT_FTPSSLAUTH => CURLFTPAUTH_TLS,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectionOptions->timeout(),
        ]);

        if ($this->connectionOptions->ssl()) {
            $this->connection->setOption(CURLOPT_USE_SSL, CURLFTPSSL_ALL);
        }

        if ( ! $this->connectionOptions->passive()) {
            $this->connection->setOption(CURLOPT_FTPPORT, '-');
        }

        if ($this->connectionOptions->ignorePassiveAddress() != null) {
            $this->connection->setOption(CURLOPT_FTP_SKIP_PASV_IP, $this->connectionOptions->ignorePassiveAddress());
        }

        $this->connection->setOption(CURLOPT_SSL_VERIFYHOST, $this->connectionOptions->sslVerifyHost());
        $this->connection->setOption(CURLOPT_SSL_VERIFYPEER, $this->connectionOptions->sslVerifyPeer());

        if ($proxyUrl = $this->connectionOptions->proxyHost()) {
            $proxyPort = $this->connectionOptions->proxyPort();
            $this->connection->setOption(CURLOPT_PROXY, $proxyPort ? $proxyUrl . ':' . $proxyPort : $proxyUrl);
            $this->connection->setOption(CURLOPT_HTTPPROXYTUNNEL, true);
        }

        if ($username = $this->connectionOptions->proxyUsername()) {
            $this->connection->setOption(CURLOPT_PROXYUSERPWD, $username . ':' . $this->connectionOptions->proxyPassword());
        }

        if ($this->connectionOptions->verbose()) {
            $this->connection->setOption(CURLOPT_VERBOSE, $this->connectionOptions->verbose());
        }

        $this->pingConnection();
        $this->setUtf8Mode();
        $this->setConnectionRoot();
    }

    /**
     * Check the connection is established.
     */
    protected function pingConnection(): void
    {
        if ($this->connection->exec() === false) {
            throw new RuntimeException('Could not connect to host: ' . $this->connectionOptions->host() . ', port:' . $this->connectionOptions->port() . '. Error : ' . $this->connection->lastError());
        }
    }

    protected function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->exec() !== false;
    }

    /**
     * Set the connection to UTF-8 mode.
     */
    protected function setUtf8Mode(): void
    {
        if ( ! $this->connectionOptions->utf8()) {
            return;
        }

        $response = $this->rawCommand($this->connection, 'OPTS UTF8 ON');
        [$code] = explode(' ', end($response), 2);
        if ( ! in_array($code, ['200', '202'])) {
            throw new RuntimeException(
                'Could not set UTF-8 mode for connection: ' . $this->connectionOptions->host() . '::' . $this->connectionOptions->port()
            );
        }
    }

    /**
     * Set the connection root.
     */
    protected function setConnectionRoot(): void
    {
        $this->rootDirectory = $root = $this->connectionOptions->root();
        if (empty($root)) {
            return;
        }

        $response = $this->rawCommand($this->connection, 'CWD ' . $root);
        [$code] = explode(' ', end($response), 2);
        if ((int) $code !== 250) {
            throw UnableToResolveConnectionRoot::itDoesNotExist($root, 'Root is invalid or does not exist');
        }
    }

    /**
     * Sends an arbitrary command to an FTP server.
     */
    protected function rawCommand(Curl $connection, string $command): array
    {
        $response = '';
        $callback = static function ($curl, $string) use (&$response) {
            $response .= $string;

            return strlen($string);
        };
        $connection->exec([
            CURLOPT_CUSTOMREQUEST => $command,
            CURLOPT_HEADERFUNCTION => $callback,
        ]);

        return explode(PHP_EOL, trim($response));
    }

    /**
     * Sends an arbitrary command to an FTP server using POSTQUOTE option. This makes sure all commands are run
     * in succession and increases chance of success for complex operations like "move/rename file".
     */
    protected function rawPost(Curl $connection, array $commandsArray): array
    {
        $response = '';
        $callback = function ($curl, $string) use (&$response) {
            $response .= $string;

            return strlen($string);
        };
        $connection->exec([
            CURLOPT_POSTQUOTE => $commandsArray,
            CURLOPT_HEADERFUNCTION => $callback,
        ]);

        return explode(PHP_EOL, trim($response));
    }
    /**
     * @return resource
     */
    private function connection()
    {
        start:
        if ($this->connection === null) {
            $this->connect();
            $this->prefixer = new PathPrefixer($this->rootDirectory);

            return $this->connection;
        }

        if ( ! $this->isConnected()) {
            $this->connection = null;
            goto start;
        }

        $this->setConnectionRoot();

        return $this->connection;
    }

    private function isPureFtpdServer(): bool
    {
        if ($this->isPureFtpdServer !== null) {
            return $this->isPureFtpdServer;
        }

        $response = $this->rawCommand($this->connection(), 'HELP');
        $response = end($response);
        $this->isPureFtpdServer = stripos($response, 'Pure-FTPd') !== false;

        return $this->isPureFtpdServer;
    }

    private function isServerSupportingListOptions(): bool
    {
        if ($this->useRawListOptions !== null) {
            return $this->useRawListOptions;
        }

        $response = $this->rawCommand($this->connection(), 'SYST');
        $syst = implode(' ', $response);

        return $this->useRawListOptions = stripos($syst, 'FileZilla') === false
            && stripos($syst, 'L8') === false;
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->fileSize($path);

            return true;
        } catch (UnableToRetrieveMetadata $exception) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $writeStream = fopen('php://temp', 'w+b');
            fwrite($writeStream, $contents);
            rewind($writeStream);
            $this->writeStream($path, $writeStream, $config);
        } finally {
            isset($writeStream) && is_resource($writeStream) && fclose($writeStream);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $this->ensureParentDirectoryExists($path, $config->get(Config::OPTION_DIRECTORY_VISIBILITY));
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, 'creating parent directory failed', $exception);
        }

        $location = $this->prefixer()->prefixPath($path);

        $result = $this->connection()->exec([
            CURLOPT_URL => $this->connectionOptions->baseUrl() . $this->separator . rawurlencode($location),
            CURLOPT_UPLOAD => 1,
            CURLOPT_INFILE => $contents,
        ]);

        if ($result === false) {
            throw UnableToWriteFile::atLocation($path, $this->connection->lastError());
        }

        if ( ! $visibility = $config->get(Config::OPTION_VISIBILITY)) {
            return;
        }

        try {
            $this->setVisibility($path, (string) $visibility);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, 'setting visibility failed', $exception);
        }
    }

    public function read(string $path): string
    {
        $readStream = $this->readStream($path);
        $contents = stream_get_contents($readStream);
        fclose($readStream);

        return $contents;
    }

    public function readStream(string $path)
    {
        $location = $this->prefixer()->prefixPath($path);
        $stream = fopen('php://temp', 'w+b');
        $result = $this->connection()->exec([
            CURLOPT_URL => $this->connectionOptions->baseUrl() . $this->separator . rawurlencode($location),
            CURLOPT_FILE => $stream,
        ]);

        if ( ! $result) {
            fclose($stream);

            throw UnableToReadFile::fromLocation($path, $this->connection->lastError());
        }

        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $connection = $this->connection();
        $this->deleteFile($path, $connection);
    }

    /**
     * @param resource $connection
     */
    private function deleteFile(string $path, $connection): void
    {
        $location = $this->prefixer()->prefixPath($path);
        $connection = $this->connection();

        $response = $this->rawCommand($connection, 'DELE ' . $location);
        [$code] = explode(' ', end($response), 2);

        if ((int) $code !== 250) {
            throw UnableToDeleteFile::atLocation($path, 'the file still exists');
        }
    }

    public function deleteDirectory(string $path): void
    {
        /** @var StorageAttributes[] $contents */
        $contents = $this->listContents($path, true);
        $connection = $this->connection();
        $directories = [$path];

        foreach ($contents as $item) {
            if ($item->isDir()) {
                $directories[] = $item->path();
                continue;
            }
            try {
                $this->deleteFile($item->path(), $connection);
            } catch (Throwable $exception) {
                throw UnableToDeleteDirectory::atLocation($path, 'unable to delete child', $exception);
            }
        }

        rsort($directories);

        foreach ($directories as $directory) {
            $directoryLocation = $this->prefixer()->prefixPath($directory);
            $response = $this->rawCommand($connection, 'RMD ' . $directoryLocation);
            [$code] = explode(' ', end($response), 2);
            if ((int) $code !== 250) {
                throw UnableToDeleteDirectory::atLocation($path, "Could not delete directory $directory");
            }
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->ensureDirectoryExists($path, $config->get('directory_visibility', $config->get('visibility')));
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $location = $this->prefixer()->prefixPath($path);
        $mode = $this->visibilityConverter->forFile($visibility);

        $connection = $this->connection();
        $request = sprintf('SITE CHMOD %o %s', $mode, $location);
        $response = $this->rawCommand($connection, $request);
        [$code, $errorMessage] = explode(' ', end($response), 2);
        if ((int) $code !== 200) {
            $errorMessage = 'unable to chmod the file by running SITE CHMOD: ' . $errorMessage;
            throw UnableToSetVisibility::atLocation($path, $errorMessage);
        }
    }

    private function fetchMetadata(string $path, string $type): FileAttributes
    {
        $location = $this->prefixer()->prefixPath($path);

        if ($this->isPureFtpdServer()) {
            $location = str_replace(' ', '\ ', $location);
            $location = $this->escapePath($location);
        }

        $connection = $this->connection();
        $object = $this->rawCommand($connection, 'STAT ' . $location);

        if (empty($object) || count($object) < 4 || substr($object[1], 0, 5) === "ftpd:") {
            throw UnableToRetrieveMetadata::create($path, $type, $this->connection->lastError() ?? '');
        }

        $attributes = $this->normalizeObject($object[2], '');

        if ( ! $attributes instanceof FileAttributes) {
            throw UnableToRetrieveMetadata::create(
                $path,
                $type,
                'expected file, ' . ($attributes instanceof DirectoryAttributes ? 'directory found' : 'nothing found')
            );
        }

        return $attributes;
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $mimetype = $this->detectMimeTypeUsingPath
                ? $this->mimeTypeDetector->detectMimeTypeFromPath($path)
                : $this->mimeTypeDetector->detectMimeType($path, $this->read($path));
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::mimeType($path, $exception->getMessage(), $exception);
        }

        if ($mimetype === null) {
            throw UnableToRetrieveMetadata::mimeType($path, 'Unknown.');
        }

        return new FileAttributes($path, null, null, null, $mimetype);
    }

    public function lastModified(string $path): FileAttributes
    {
        $location = $this->prefixer()->prefixPath($path);
        $connection = $this->connection();

        $response = $this->rawCommand($connection, 'MDTM ' . $location);
        [$code, $time] = explode(' ', end($response), 2);
        if ($code !== '213') {
            throw UnableToRetrieveMetadata::lastModified($path);
        }

        if (strpos($time, '.')) {
            $lastModified = DateTime::createFromFormat('YmdHis.u', $time);
        } else {
            $lastModified = DateTime::createFromFormat('YmdHis', $time);
        }

        if ( ! $lastModified) {
            throw UnableToRetrieveMetadata::lastModified($lastModified);
        }

        return new FileAttributes($path, null, null, $lastModified->getTimestamp());
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->fetchMetadata($path, FileAttributes::ATTRIBUTE_VISIBILITY);
    }

    public function fileSize(string $path): FileAttributes
    {
        $location = $this->prefixer()->prefixPath($path);
        $response = $this->rawCommand($this->connection(), 'SIZE ' . $location);
        [$code, $fileSize] = explode(' ', end($response), 2);
        if ($code != '213') {
            throw UnableToRetrieveMetadata::fileSize($path, $fileSize);
        }

        if ($fileSize < 0) {
            throw UnableToRetrieveMetadata::fileSize($path, '');
        }

        return new FileAttributes($path, (int) $fileSize);
    }

    private function ftpRawlist(string $options, string $path): array
    {
        $path = rtrim($path, '/') . '/';

        if ($this->isPureFtpdServer()) {
            $path = str_replace(' ', '\ ', $path);
            $path = $this->escapePath($path);
        }

        if ( ! $this->isServerSupportingListOptions()) {
            $options = '';
        }

        $request = rtrim('LIST ' . $options . $path);
        $listing = $this->connection()->exec([CURLOPT_CUSTOMREQUEST => $request]);

        return explode(PHP_EOL, $listing);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $path = ltrim($path, '/');
        $path = $path === '' ? $path : trim($path, '/') . '/';

        if ($deep && $this->connectionOptions->recurseManually()) {
            yield from $this->listDirectoryContentsRecursive($path);
        } else {
            $location = $this->prefixer()->prefixPath($path);
            $options = $deep ? '-alnR' : '-aln';

            $listing = $this->ftpRawlist($options, $location);

            yield from $this->normalizeListing($listing, $path);
        }
    }

    private function normalizeListing(array $listing, string $prefix = ''): Generator
    {
        $base = $prefix;

        foreach ($listing as $item) {
            if ($item === '' || preg_match('#.* \.(\.)?$|^total#', $item)) {
                continue;
            }

            if (preg_match('#^.*:$#', $item)) {
                $base = preg_replace('~^\./*|:$~', '', $item);
                continue;
            }

            yield $this->normalizeObject($item, $base);
        }
    }

    private function normalizeObject(string $item, string $base): StorageAttributes
    {
        $this->systemType === null && $this->systemType = $this->detectSystemType($item);

        if ($this->systemType === self::SYSTEM_TYPE_UNIX) {
            return $this->normalizeUnixObject($item, $base);
        }

        return $this->normalizeWindowsObject($item, $base);
    }

    private function detectSystemType(string $item): string
    {
        return preg_match(
            '/^[0-9]{2,4}-[0-9]{2}-[0-9]{2}/',
            $item
        ) ? self::SYSTEM_TYPE_WINDOWS : self::SYSTEM_TYPE_UNIX;
    }

    private function normalizeWindowsObject(string $item, string $base): StorageAttributes
    {
        $item = preg_replace('#\s+#', ' ', trim($item), 3);
        $parts = explode(' ', $item, 4);

        if (count($parts) !== 4) {
            throw new RuntimeException("Metadata can't be parsed from item '$item' , not enough parts.");
        }

        [$date, $time, $size, $name] = $parts;
        $path = $base === '' ? $name : rtrim($base, '/') . '/' . $name;

        if ($size === '<DIR>') {
            return new DirectoryAttributes($path);
        }

        // Check for the correct date/time format
        $format = strlen($date) === 8 ? 'm-d-yH:iA' : 'Y-m-dH:i';
        $dateTime = DateTime::createFromFormat($format, $date . $time);
        $lastModified = $dateTime ? $dateTime->getTimestamp() : (int) strtotime("$date $time");

        return new FileAttributes($path, (int) $size, null, $lastModified);
    }

    private function normalizeUnixObject(string $item, string $base): StorageAttributes
    {
        $item = preg_replace('#\s+#', ' ', trim($item), 7);
        $parts = explode(' ', $item, 9);

        if (count($parts) !== 9) {
            throw new RuntimeException("Metadata can't be parsed from item '$item' , not enough parts.");
        }

        [$permissions, /* $number */, /* $owner */, /* $group */, $size, $month, $day, $timeOrYear, $name] = $parts;
        $isDirectory = $this->listingItemIsDirectory($permissions);
        $permissions = $this->normalizePermissions($permissions);
        $path = $base === '' ? $name : rtrim($base, '/') . '/' . $name;
        $lastModified = $this->connectionOptions->timestampsOnUnixListingsEnabled() ? $this->normalizeUnixTimestamp(
            $month,
            $day,
            $timeOrYear
        ) : null;

        if ($isDirectory) {
            return new DirectoryAttributes(
                $path,
                $this->visibilityConverter->inverseForDirectory($permissions),
                $lastModified
            );
        }

        $visibility = $this->visibilityConverter->inverseForFile($permissions);

        return new FileAttributes($path, (int) $size, $visibility, $lastModified);
    }

    private function listingItemIsDirectory(string $permissions): bool
    {
        return substr($permissions, 0, 1) === 'd';
    }

    private function normalizeUnixTimestamp(string $month, string $day, string $timeOrYear): int
    {
        if (is_numeric($timeOrYear)) {
            $year = $timeOrYear;
            $hour = '00';
            $minute = '00';
            $seconds = '00';
        } else {
            $year = date('Y');
            [$hour, $minute] = explode(':', $timeOrYear);
            $seconds = '00';
        }

        $dateTime = DateTime::createFromFormat('Y-M-j-G:i:s', "{$year}-{$month}-{$day}-{$hour}:{$minute}:{$seconds}");

        return $dateTime->getTimestamp();
    }

    private function normalizePermissions(string $permissions): int
    {
        // remove the type identifier
        $permissions = substr($permissions, 1);

        // map the string rights to the numeric counterparts
        $map = ['-' => '0', 'r' => '4', 'w' => '2', 'x' => '1'];
        $permissions = strtr($permissions, $map);

        // split up the permission groups
        $parts = str_split($permissions, 3);

        // convert the groups
        $mapper = function ($part) {
            return array_sum(str_split($part));
        };

        // converts to decimal number
        return octdec(implode('', array_map($mapper, $parts)));
    }

    /**
     * @inheritdoc
     *
     * @param string $directory
     */
    private function listDirectoryContentsRecursive(string $directory): Generator
    {
        $location = $this->prefixer()->prefixPath($directory);
        $listing = $this->ftpRawlist('-aln', $location);
        /** @var StorageAttributes[] $listing */
        $listing = $this->normalizeListing($listing, $directory);

        foreach ($listing as $item) {
            yield $item;

            if ( ! $item->isDir()) {
                continue;
            }

            $children = $this->listDirectoryContentsRecursive($item->path());

            foreach ($children as $child) {
                yield $child;
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->ensureParentDirectoryExists($destination, $config->get(Config::OPTION_DIRECTORY_VISIBILITY));
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }

        $sourceLocation = $this->prefixer()->prefixPath($source);
        $destinationLocation = $this->prefixer()->prefixPath($destination);
        $connection = $this->connection();

        $moveCommands = [
            'RNFR ' . $sourceLocation,
            'RNTO ' . $destinationLocation,
        ];

        $response = $this->rawPost($connection, $moveCommands);
        [$code] = explode(' ', end($response), 2);

        if ((int) $code !== 250) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $readStream = $this->readStream($source);
            $visibility = $this->visibility($source)->visibility();
            $this->writeStream($destination, $readStream, new Config(compact('visibility')));
        } catch (Throwable $exception) {
            if (isset($readStream) && is_resource($readStream)) {
                @fclose($readStream);
            }
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    private function ensureParentDirectoryExists(string $path, ?string $visibility): void
    {
        $dirname = dirname($path);

        if ($dirname === '' || $dirname === '.') {
            return;
        }

        $this->ensureDirectoryExists($dirname, $visibility);
    }

    /**
     * @param string $dirname
     */
    private function ensureDirectoryExists(string $dirname, ?string $visibility): void
    {
        $connection = $this->connection();

        $dirPath = '';
        $parts = explode('/', trim($dirname, '/'));
        $mode = $visibility ? $this->visibilityConverter->forDirectory($visibility) : false;

        foreach ($parts as $part) {
            $dirPath .= '/' . $part;
            $location = $this->prefixer()->prefixPath($dirPath);

            if ($this->directoryExists($location)) {
                continue;
            }

            $response = $this->rawCommand($connection, 'MKD ' . $location);
            [$code] = explode(' ', end($response), 2);

            if ((int) $code !== 257) {
                $errorMessage = 'unable to create the directory: ' . $this->connection->lastError();
                throw UnableToCreateDirectory::atLocation($dirPath, $errorMessage);
            }

            if ($mode !== false) {
                $request = sprintf('SITE CHMOD %o %s', $mode, $location);
                $response = $this->rawCommand($connection, $request);
                [$code, $errorMessage] = explode(' ', end($response), 2);
                if ((int) $code !== 200) {
                    $errorMessage = 'unable to chmod the directory by running SITE CHMOD: ' . $errorMessage;
                    throw UnableToCreateDirectory::atLocation($dirPath, $errorMessage);
                }
            }
        }
    }

    private function escapePath(string $path): string
    {
        return str_replace(['*', '[', ']'], ['\\*', '\\[', '\\]'], $path);
    }

    public function directoryExists(string $path): bool
    {
        $response = $this->rawCommand($this->connection(), 'CWD ' . $path);
        [$code] = explode(' ', end($response), 2);

        return (int) $code === 250;
    }

    /**
     * @return PathPrefixer
     */
    private function prefixer(): PathPrefixer
    {
        if ($this->rootDirectory === null) {
            $this->connection();
        }

        return $this->prefixer;
    }
}
