<?php

declare(strict_types=1);

namespace VladimirYuldashev\Flysystem;

class CurlFtpConnectionOptions
{
    public function __construct(
        private string $host,
        private string $root,
        private string $username,
        private string $password,
        private int $port = 21,
        private bool $ftps = false, // use ftps:// with implicit TLS or ftp:// with explicit TLS
        private bool $ssl = false,
        private int $timeout = 90,
        private bool $utf8 = false,
        private bool $passive = true,
        private int $sslVerifyPeer = 0,
        private int $sslVerifyHost = 0,
        private ?string $systemType = null,
        private ?bool $ignorePassiveAddress = null,
        private bool $timestampsOnUnixListingsEnabled = false,
        private bool $recurseManually = false,
        private ?bool $useRawListOptions = null,
        private ?string $proxyHost = null,
        private ?int $proxyPort = null,
        private ?string $proxyUsername = null,
        private ?string $proxyPassword = null,
        private bool $verbose = false,
    ) {
    }

    public function host(): string
    {
        return $this->host;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function ssl(): bool
    {
        return $this->ssl;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function utf8(): bool
    {
        return $this->utf8;
    }

    public function passive(): bool
    {
        return $this->passive;
    }

    public function systemType(): ?string
    {
        return $this->systemType;
    }

    public function ignorePassiveAddress(): ?bool
    {
        return $this->ignorePassiveAddress;
    }

    public function timestampsOnUnixListingsEnabled(): bool
    {
        return $this->timestampsOnUnixListingsEnabled;
    }

    public function recurseManually(): bool
    {
        return $this->recurseManually;
    }

    public function useRawListOptions(): ?bool
    {
        return $this->useRawListOptions;
    }

    public function ftps(): bool
    {
        return $this->ftps;
    }

    public function sslVerifyPeer(): int
    {
        return $this->sslVerifyPeer;
    }

    public function sslVerifyHost(): int
    {
        return $this->sslVerifyHost;
    }

    public function proxyHost(): ?string
    {
        return $this->proxyHost;
    }

    public function proxyPort(): ?int
    {
        return $this->proxyPort;
    }

    public function proxyUsername(): ?string
    {
        return $this->proxyUsername;
    }

    public function proxyPassword(): ?string
    {
        return $this->proxyPassword;
    }

    public function verbose(): bool
    {
        return $this->verbose;
    }

    /**
     * Returns the base url of the connection.
     */
    public function baseUrl(): string
    {
        $protocol = $this->ftps() ? 'ftps' : 'ftp';

        return $protocol.'://'.$this->host().':'.$this->port();
    }

    public static function fromArray(array $options): self
    {
        return new self(
            $options['host'] ?? 'invalid://host-not-set',
            $options['root'] ?? '',
            $options['username'] ?? 'invalid://username-not-set',
            $options['password'] ?? 'invalid://password-not-set',
            $options['port'] ?? 21,
            $options['ftps'] ?? true,
            $options['ssl'] ?? false,
            $options['timeout'] ?? 90,
            $options['utf8'] ?? false,
            $options['passive'] ?? true,
            $options['sslVerifyPeer'] ?? 1,
            $options['sslVerifyHost'] ?? 2,
            $options['systemType'] ?? null,
            $options['ignorePassiveAddress'] ?? null,
            $options['timestampsOnUnixListingsEnabled'] ?? false,
            $options['recurseManually'] ?? true,
            $options['useRawListOptions'] ?? null,
            $options['proxyHost'] ?? null,
            $options['proxyPort'] ?? null,
            $options['proxyUsername'] ?? null,
            $options['proxyPassword'] ?? null,
            $options['verbose'] ?? false,
        );
    }
}
