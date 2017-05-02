# Flysystem Adapter for the FTP with cURL implementation

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vladimir-yuldashev/flysystem-curlftp.svg?style=flat-square)](https://packagist.org/packages/vladimir-yuldashev/flysystem-curlftp)
[![Build Status](https://img.shields.io/travis/vladimir-yuldashev/flysystem-curlftp/master.svg?style=flat-square)](https://travis-ci.org/vladimir-yuldashev/flysystem-curlftp)
[![StyleCI](https://styleci.io/repos/90028075/shield?branch=master)](https://styleci.io/repos/90028075)

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
  'protocol' => 'ftps',
  'host' => 'ftp.example.com',
  'port' => 990,
  'username' => 'ftp-user',
  'password' => 'ftp-password',
]);

$filesystem = new Filesystem($adapter);
```

You case use both `ftp` and `ftps` protocols. 

## Testing

``` bash
$ composer test
```

## Security

If you discover any security related issues, please email misterio92@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
