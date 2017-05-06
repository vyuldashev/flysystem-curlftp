<?php

namespace VladimirYuldashev\Flysystem;

use DateTime;
use Normalizer;
use League\Flysystem\Util;
use League\Flysystem\Config;
use League\Flysystem\Util\MimeType;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\AbstractFtpAdapter;

class CurlFtpAdapter extends AbstractFtpAdapter
{
    protected $configurable = [
        'protocol',
        'host',
        'port',
        'username',
        'password',
    ];

    /** @var string */
    protected $protocol = 'ftp';

    /** @var resource */
    protected $curl;

    /**
     * @var bool
     */
    protected $isPureFtpd;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    /**
     * Set remote protocol. ftp or ftps.
     *
     * @param string $protocol
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }

    /**
     * Establish a connection.
     */
    public function connect()
    {
        $this->connection = new Curl();
        $this->connection->setOptions([
            CURLOPT_URL => $this->getUrl(),
            CURLOPT_USERPWD => $this->getUsername() . ':' . $this->getPassword(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FTP_SSL => CURLFTPSSL_TRY,
            CURLOPT_FTPSSLAUTH => CURLFTPAUTH_TLS,
            CURLOPT_RETURNTRANSFER => true,
        ]);
    }

    /**
     * Close the connection.
     */
    public function disconnect()
    {
        if (isset($this->connection)) {
            unset($this->connection);
        }
        unset($this->isPureFtpd);
    }

    /**
     * Check if a connection is active.
     *
     * @return bool
     */
    public function isConnected()
    {
        return isset($this->connection);
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $contents);
        rewind($stream);

        $result = $this->writeStream($path, $stream, $config);

        if ($result === false) {
            return false;
        }

        $result['contents'] = $contents;
        $result['mimetype'] = Util::guessMimeType($path, $contents);

        return $result;
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        $connection = $this->getConnection();

        $result = $connection->exec([
            CURLOPT_URL => $this->getUrl() . '/' . $path,
            CURLOPT_UPLOAD => 1,
            CURLOPT_INFILE => $resource,
        ]);

        if ($result === false) {
            return false;
        }

        $type = 'file';

        return compact('type', 'path');
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        $connection = $this->getConnection();

        $result = $connection->exec([
            CURLOPT_POSTQUOTE => ['RNFR ' . $path, 'RNTO ' . $newpath],
        ]);

        return $result !== false;
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $file = $this->read($path);

        if ($file === false) {
            return false;
        }

        return $this->write($newpath, $file['contents'], new Config()) !== false;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $connection = $this->getConnection();

        $result = $connection->exec([
            CURLOPT_POSTQUOTE => ['DELE ' . $path],
        ]);

        return $result !== false;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $connection = $this->getConnection();

        $result = $connection->exec([
            CURLOPT_POSTQUOTE => ['RMD ' . $dirname],
        ]);

        return $result !== false;
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        $connection = $this->getConnection();

        $result = $connection->exec([
            CURLOPT_POSTQUOTE => ['MKD ' . $dirname],
        ]);

        if ($result === false) {
            return false;
        }

        return ['type' => 'dir', 'path' => $dirname];
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        if ($visibility === AdapterInterface::VISIBILITY_PUBLIC) {
            $mode = $this->getPermPublic();
        } else {
            $mode = $this->getPermPrivate();
        }

        $request = sprintf('SITE CHMOD %o %s', $mode, $path);
        $response = $this->rawCommand($request);
        list($code, $message) = explode(' ', end($response), 2);
        if ($code !== '200') {
            return false;
        }

        return $this->getMetadata($path);
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        if (!$object = $this->readStream($path)) {
            return false;
        }

        $object['contents'] = stream_get_contents($object['stream']);
        fclose($object['stream']);
        unset($object['stream']);

        return $object;
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        $stream = fopen('php://temp', 'w+b');

        $connection = $this->getConnection();

        $result = $connection->exec([
            CURLOPT_URL => $this->getUrl() . '/' . $path,
            CURLOPT_FILE => $stream,
        ]);

        if (!$result) {
            fclose($stream);

            return false;
        }

        rewind($stream);

        return ['type' => 'file', 'path' => $path, 'stream' => $stream];
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        if ($path === '') {
            return ['type' => 'dir', 'path' => ''];
        }

        $request = rtrim('LIST -A ' . $this->normalizePath($path));

        $connection = $this->getConnection();
        $result = $connection->exec([CURLOPT_CUSTOMREQUEST => $request]);
        if ($result === false) {
            return false;
        }
        $listing = $this->normalizeListing(explode(PHP_EOL, $result), '');

        return current($listing);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        if (!$metadata = $this->getMetadata($path)) {
            return false;
        }

        $metadata['mimetype'] = MimeType::detectByFilename($path);

        return $metadata;
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        $response = $this->rawCommand('MDTM ' . $path);
        list($code, $time) = explode(' ', end($response), 2);
        if ($code !== '213') {
            return false;
        }

        $datetime = DateTime::createFromFormat('YmdHis', $time);

        return ['path' => $path, 'timestamp' => $datetime->getTimestamp()];
    }

    /**
     * {@inheritdoc}
     *
     * @param string $directory
     */
    protected function listDirectoryContents($directory, $recursive = false)
    {
        if ($recursive === true) {
            return $this->listDirectoryContentsRecursive($directory);
        }

        $request = rtrim('LIST -aln ' . $this->normalizePath($directory));

        $connection = $this->getConnection();
        $result = $connection->exec([CURLOPT_CUSTOMREQUEST => $request]);
        if ($result === false) {
            return false;
        }

        if ($directory === '/') {
            $directory = '';
        }

        return $this->normalizeListing(explode(PHP_EOL, $result), $directory);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $directory
     */
    protected function listDirectoryContentsRecursive($directory)
    {
        $request = rtrim('LIST -aln ' . $this->normalizePath($directory));

        $connection = $this->getConnection();
        $result = $connection->exec([CURLOPT_CUSTOMREQUEST => $request]);

        $listing = $this->normalizeListing(explode(PHP_EOL, $result), $directory);
        $output = [];

        foreach ($listing as $item) {
            if ($item['type'] === 'file') {
                $output[] = $item;
            } elseif ($item['type'] === 'dir') {
                $output = array_merge($output, $this->listDirectoryContentsRecursive($item['path']));
            }
        }

        return $output;
    }

    /**
     * Normalize path depending on server.
     *
     * @param string $path
     *
     * @return string
     */
    protected function normalizePath($path)
    {
        if (empty($path)) {
            return '';
        }
        $path = Normalizer::normalize($path);

        if ($this->isPureFtpdServer()) {
            $path = str_replace(' ', '\ ', $path);
        }

        $path = str_replace('*', '\\*', $path);

        return $path;
    }

    /**
     * @return bool
     */
    protected function isPureFtpdServer()
    {
        if (!isset($this->isPureFtpd)) {
            $response = $this->rawCommand('HELP');
            $response = end($response);
            $this->isPureFtpd = stripos($response, 'Pure-FTPd') !== false;
        }

        return $this->isPureFtpd;
    }

    /**
     * Sends an arbitrary command to an FTP server.
     *
     * @param  string $command The command to execute
     * @return array Returns the server's response as an array of strings
     */
    protected function rawCommand($command)
    {
        $response = '';
        $callback = function ($ch, $string) use (&$response) {
            $response .= $string;

            return strlen($string);
        };
        $connection = $this->getConnection();
        $connection->exec([
            CURLOPT_CUSTOMREQUEST => $command,
            CURLOPT_HEADERFUNCTION => $callback,
        ]);

        return explode(PHP_EOL, trim($response));
    }

    protected function getUrl()
    {
        return $this->protocol . '://' . $this->getHost() . ':' . $this->getPort();
    }
}
