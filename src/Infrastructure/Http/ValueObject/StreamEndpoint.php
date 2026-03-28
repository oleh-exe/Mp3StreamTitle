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
    private string $scheme;

    private string $host;

    private int $port;

    private string $path;

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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }
}