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

namespace Mp3StreamTitle\Domain\ValueObject;

use InvalidArgumentException;
use ValueError;

final readonly class StreamEndpoint
{
    /**
     * @var Scheme
     */
    private Scheme $scheme;

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
     * @param Scheme $scheme The scheme object associated with the URL.
     * @param string $host The host component of the URL.
     * @param int $port The port number for the connection.
     * @param string $path The path component of the URL.
     *
     * @return void
     */
    private function __construct(
        private string $url,
        Scheme $scheme,
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
     * Creates an instance from a given URL string.
     *
     * @param string $url The URL to parse and construct the object from.
     *
     * @return self The constructed instance based on the provided URL.
     *
     * @throws InvalidArgumentException If the URL is invalid, cannot be parsed,
     *                                  contains unsupported userinfo, lacks a scheme,
     *                                  or includes an invalid host, scheme, or port.
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

        if (
            isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'Userinfo in URL is not supported'
            );
        }

        if (!isset($parts['scheme'])) {
            throw new InvalidArgumentException(
                'URL must contain a scheme'
            );
        }

        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? null;
        $port = $parts['port'] ?? null;

        try {
            $scheme = Scheme::from(strtolower($parts['scheme']));
        } catch (ValueError) {
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

        $host = strtolower($host);

        if ($port === null) {
            $port = $scheme->defaultPort();
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

        if ($path === '') {
            $path = '/';
        }

        if (
            $query !== null
            && $query !== ''
        ) {
            $path = $path . '?' . $query;
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
     * Retrieves the scheme.
     *
     * @return Scheme The scheme associated with this instance.
     */
    public function getScheme(): Scheme
    {
        return $this->scheme;
    }

    /**
     * Retrieves the transport.
     *
     * @return Transport The transport derived from the current scheme.
     */
    public function getTransport(): Transport
    {
        return $this->scheme->toTransport();
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
    public function getRequestTarget(): string
    {
        return $this->path;
    }

    /**
     * Determines if the current scheme is secure.
     *
     * @return bool True if the scheme is secure, false otherwise.
     */
    public function isSecure(): bool
    {
        return $this->scheme->isSecure();
    }
}