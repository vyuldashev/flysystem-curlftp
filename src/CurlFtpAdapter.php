<?php

namespace VladimirYuldashev\Flysystem;

use DateTime;
use League\Flysystem\Util;
use League\Flysystem\Config;
use League\Flysystem\Util\MimeType;
use League\Flysystem\AdapterInterface;
use League\Flysystem\NotSupportedException;
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

    protected $protocol;
    protected $curl;

    protected $permPublic = 744;
    protected $permPrivate = 700;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->connect();
    }

    /**
     * Set remote protocol. ftp or ftps.
     *
     * @param $protocol
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
        if ($this->isConnected()) {
            $this->disconnect();
        }

        $this->curl = curl_init();

        curl_setopt($this->curl, CURLOPT_URL, $this->getUrl());
        curl_setopt($this->curl, CURLOPT_USERPWD, $this->getUsername().':'.$this->getPassword());
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curl, CURLOPT_FTP_SSL, CURLFTPSSL_TRY);
        curl_setopt($this->curl, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Close the connection.
     */
    public function disconnect()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }

    /**
     * Check if a connection is active.
     *
     * @return bool
     */
    public function isConnected()
    {
        return is_resource($this->curl);
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
        curl_setopt($this->curl, CURLOPT_URL, $this->getUrl().'/'.$path);
        curl_setopt($this->curl, CURLOPT_UPLOAD, 1);
        curl_setopt($this->curl, CURLOPT_INFILE, $resource);
        $result = curl_exec($this->curl);

        $this->connect();

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
        curl_setopt($this->curl, CURLOPT_POSTQUOTE, ['RNFR '.$path, 'RNTO '.$newpath]);

        $result = curl_exec($this->curl);

        $this->connect();

        return $result;
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

        return $this->write($newpath, $file['contents'], new Config());
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
        curl_setopt($this->curl, CURLOPT_POSTQUOTE, ['DELE '.$path]);

        $result = curl_exec($this->curl);

        $this->connect();

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
        curl_setopt($this->curl, CURLOPT_POSTQUOTE, ['RMD '.$dirname]);

        $result = curl_exec($this->curl);

        $this->connect();

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
        curl_setopt($this->curl, CURLOPT_POSTQUOTE, ['MKD '.$dirname]);

        $result = curl_exec($this->curl);

        $this->connect();

        return $result !== false;
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
        $mode = $visibility === AdapterInterface::VISIBILITY_PUBLIC ? $this->getPermPublic() : $this->getPermPrivate();

        curl_setopt($this->curl, CURLOPT_POSTQUOTE, ['SITE CHMOD '.$mode.' '.$path]);
        $result = curl_exec($this->curl);

        $this->connect();

        if ($result === false) {
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

        curl_setopt($this->curl, CURLOPT_URL, $this->getUrl().'/'.$path);
        curl_setopt($this->curl, CURLOPT_FILE, $stream);
        $result = curl_exec($this->curl);

        $this->connect();

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

        $directory = '';
        $pathParts = explode('/', $path);

        if (count($pathParts) > 1) {
            $directory = array_slice($pathParts, 0, count($pathParts) - 1);
            $directory = implode('/', $directory);
        }

        $listing = $this->listDirectoryContents($directory);

        if ($listing === false) {
            return false;
        }

        $file = array_filter($listing, function ($item) use ($path) {
            return $item['path'] === $path;
        });

        return current($file);
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
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'MDTM '.$path);

        $response = '';
        curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, function ($ch, $string) use (&$response) {
            $length = strlen($string);
            $response .= $string;

            return $length;
        });
        curl_exec($this->curl);

        $response = explode(PHP_EOL, trim($response));
        $item = end($response);

        $this->connect();

        list($code, $time) = explode(' ', $item);
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

        $request = strlen($directory) > 0 ? 'LIST '.$directory : 'LIST';

        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $request);
        $result = curl_exec($this->curl);

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
        $request = strlen($directory) > 0 ? 'LIST '.$directory : 'LIST';

        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $request);
        $result = curl_exec($this->curl);
        var_dump($result);
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
     * Normalize a file entry.
     *
     * @param string $item
     * @param string $base
     *
     * @return array normalized file array
     *
     * @throws NotSupportedException
     */
    protected function normalizeObject($item, $base)
    {
        $object = parent::normalizeObject($item, $base);

        if ($timestamp = $this->getTimestamp($object['path'])) {
            $object['timestamp'] = $timestamp['timestamp'];
        }

        return $object;
    }

    protected function getUrl()
    {
        return $this->protocol.'://'.$this->getHost().':'.$this->getPort();
    }
}
