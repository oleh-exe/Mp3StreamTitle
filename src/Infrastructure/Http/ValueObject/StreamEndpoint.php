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
    private function __construct(private string $url)
    {
    }

    public static function fromString(string $url): self
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(
                sprintf('Invalid URL provided: "%s"', $url)
            );
        }

        $parts = parse_url($url);

        if (!in_array($parts['scheme'], ['http', 'https'], true)) {
            throw new InvalidArgumentException('Invalid scheme');
        }

        return new self($url);
    }

    public function getHost(): string
    {
        return parse_url($this->url, PHP_URL_HOST);
    }

    public function getPort(): int
    {
        return parse_url($this->url, PHP_URL_PORT);
    }

    public function getPath(): string
    {
        return parse_url($this->url, PHP_URL_PATH);
    }

    public function isSecure(): bool
    {
        $scheme = parse_url($this->url, PHP_URL_SCHEME);

        return $scheme === 'https';
    }
}