<?php
/**
 * Copyright 2026 Oleh Kovalenko
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Mp3StreamTitle\Infrastructure\Http\ValueObject;

use InvalidArgumentException;

final readonly class StreamEndpoint
{
    /**
     * @var string
     */
    private string $scheme;

    /**
     * @var string
     */
    private string $host;

    /**
     * @var int
     */
    private int $port;

    /**
     * @var string
     */
    private string $path;

    /**
     * Initializes a new instance of the class with the specified parameters.
     *
     * @param string $url The full URL string.
     * @param string $scheme The scheme component of the URL.
     * @param string $host The host component of the URL.
     * @param int $port The port component of the URL.
     * @param string $path The path component of the URL.
     *
     * @return void
     */
    private function __construct(
        private string $url,
        string $scheme,
        string $host,
        int $port,
        string $path
    ) {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
    }

    /**
     * Creates an instance of the class using a URL string.
     *
     * @param string $url The URL string to be parsed and used for instantiation.
     *
     * @return self An instance of the class initialized with the parsed URL components.
     *
     * @throws InvalidArgumentException If the provided URL is invalid or contains unsupported components.
     */
    public static function fromString(string $url): self
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(
                sprintf('Invalid URL provided: "%s"', $url)
            );
        }

        $parts = parse_url($url);

        if ($parts === false) {
            throw new InvalidArgumentException(
                sprintf('Unable to parse URL: "%s"', $url)
            );
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? '/';
        $port = $parts['port'] ?? null;

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                'Invalid or unsupported URL scheme'
            );
        }

        if (
            !is_string($host)
            || ($host === '')
        ) {
            throw new InvalidArgumentException(
                'URL must contain a valid host'
            );
        }

        if ($port === null) {
            $port = $scheme === 'https' ? 443 : 80;
        }

        if (
            !is_int($port)
            || ($port <= 0)
            || ($port > 65535)
        ) {
            throw new InvalidArgumentException(
                'Invalid port in URL'
            );
        }

        if (
            !is_string($path)
            || ($path === '')
        ) {
            $path = '/';
        }

        return new self(
            url: $url,
            scheme: $scheme,
            host: $host,
            port: $port,
            path: $path
        );
    }

    /**
     * Retrieves the URL.
     *
     * @return string The URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Retrieves the scheme component.
     *
     * @return string The scheme component.
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Determines if the scheme is secure.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }
}