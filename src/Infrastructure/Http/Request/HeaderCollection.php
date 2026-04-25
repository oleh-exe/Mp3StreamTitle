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

namespace Mp3StreamTitle\Infrastructure\Http\Request;

use InvalidArgumentException;

final readonly class HeaderCollection
{
    /**
     * @var array<string, string>
     */
    private array $headers;

    public function __construct(array $headers)
    {
        $this->headers = $this->normalizeAndValidateHeaders($headers);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return array_key_exists($this->normalizeHeaderName($name), $this->headers);
    }

    /**
     * @param string $name
     * @return string
     */
    public function get(string $name): string
    {
        $normalized = $this->normalizeHeaderName($name);

        if (!isset($this->headers[$normalized])) {
            throw new InvalidArgumentException(
                sprintf('Header "%s" not found', $name)
            );
        }

        return $this->headers[$normalized];
    }

    /**
     * @param string $name
     * @param string $value
     * @return self
     */
    public function with(string $name, string $value): self
    {
        $this->assertValidHeaderName($name);
        $this->assertValidHeaderValue($value);

        $normalized = $this->normalizeHeaderName($name);

        $new = $this->headers;
        $new[$normalized] = $value;

        return new self($new);
    }

    /**
     * @param array $headers
     * @return array
     */
    private function normalizeAndValidateHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name)) {
                throw new InvalidArgumentException(
                    'Header names must be strings'
                );
            }

            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    sprintf('Header "%s" value must be a string', $name)
                );
            }

            $this->assertValidHeaderName($name);
            $this->assertValidHeaderValue($value);

            $normalizedName = $this->normalizeHeaderName($name);

            if (isset($normalized[$normalizedName])) {
                throw new InvalidArgumentException(
                    sprintf('Duplicate header "%s"', $normalizedName)
                );
            }

            $normalized[$normalizedName] = $value;
        }

        return $normalized;
    }

    /**
     * @param string $name
     * @return void
     */
    private function assertValidHeaderName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException(
                'Header name cannot be empty'
            );
        }

        if (!preg_match('/^[A-Za-z0-9!#$%&\'*+\-.^_`|~]+$/', $name)) {
            throw new InvalidArgumentException(
                sprintf('Invalid header name "%s"', $name)
            );
        }
    }

    /**
     * @param string $value
     * @return void
     */
    private function assertValidHeaderValue(string $value): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException(
                'Header value must not contain CR or LF characters'
            );
        }
    }

    /**
     * @param string $name
     * @return string
     */
    private function normalizeHeaderName(string $name): string
    {
        $splitName = array_map(
            static fn(string $part): string => ucfirst(strtolower($part)),
            explode('-', $name)
        );

        return implode('-', $splitName);
    }
}