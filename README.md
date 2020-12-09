# Flysystem Adapter for the FTP with cURL implementation

[![Latest Stable Version](https://poser.pugx.org/vyuldashev/flysystem-curlftp/v/stable?format=flat-square)](https://packagist.org/packages/vyuldashev/flysystem-curlftp)
[![Build Status](https://github.com/vyuldashev/flysystem-curlftp/workflows/Tests/badge.svg)](https://github.com/vyuldashev/flysystem-curlftp/actions)
[![StyleCI](https://styleci.io/repos/90028075/shield?branch=master)](https://styleci.io/repos/90028075)
[![Quality Score](https://img.shields.io/scrutinizer/g/vyuldashev/flysystem-curlftp.svg?style=flat-square)](https://scrutinizer-ci.com/g/vyuldashev/flysystem-curlftp)
[![License](https://poser.pugx.org/vyuldashev/flysystem-curlftp/license?format=flat-square)](https://packagist.org/packages/vyuldashev/flysystem-curlftp)

This package contains a [Flysystem](https://flysystem.thephpleague.com/) FTP adapter with cURL implementation.
It supports both explicit and implicit SSL connections.

## Installation

You can install the package via composer:

``` bash
composer require vladimir-yuldashev/flysystem-curlftp
```

## Usage

``` php
use League\Flysystem\Filesystem;
use VladimirYuldashev\Flysystem\CurlFtpAdapter;

$adapter = new CurlFtpAdapter([
  'host' => 'ftp.example.com',
  'username' => 'username',
  'password' => 'password',

  /** optional config settings */
  'port' => 21,
  'root' => '/path/to/root',
  'utf8' => true,
  'ssl' => true,
  'timeout' => 90,		// connect timeout
  'sslVerifyPeer' => 0, // using 0 is insecure, use it only if you know what you're doing
  'sslVerifyHost' => 0, // using 0 is insecure, use it only if you know what you're doing
  
  /** proxy settings */
  'proxyHost' => 'proxy-server.example.com',
  'proxyPort' => 80,
  'proxyUsername' => 'proxyuser',
  'proxyPassword' => 'proxypassword',
]);

$filesystem = new Filesystem($adapter);
``` 

## Testing

``` bash
$ composer test
```

## Security

If you discover any security related issues, please email misterio92@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
