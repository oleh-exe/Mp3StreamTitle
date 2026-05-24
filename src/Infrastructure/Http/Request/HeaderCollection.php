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

    /**
     * @param array $headers An array of headers to be normalized and validated.
     *
     * @return void
     */
    public function __construct(array $headers)
    {
        $this->headers = $this->normalizeAndValidateHeaders($headers);
    }

    /**
     * Retrieves all the headers.
     *
     * @return array An associative array containing all headers.
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * Checks if a specific header exists.
     *
     * @param string $name The name of the header to check for existence.
     *
     * @return bool True if the header exists, false otherwise.
     */
    public function has(string $name): bool
    {
        return array_key_exists($this->normalizeHeaderName($name), $this->headers);
    }

    /**
     * Retrieves the value of the specified header.
     *
     * @param string $name The name of the header to retrieve.
     *
     * @return string The value of the specified header.
     *
     * @throws InvalidArgumentException If the specified header does not exist.
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
     * Returns a new instance with the specified header.
     *
     * @param string $name The name of the header.
     * @param string $value The value of the header.
     *
     * @return self A new instance with the updated header.
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
     * Normalizes and validates the provided headers.
     *
     * This method ensures that all header names and values are strings,
     * validates their format, and normalizes header names for consistent usage.
     * It also checks for and prevents duplicate headers.
     *
     * @param array $headers An associative array of headers where the key
     *                       represents the header name and the value
     *                       represents the header value.
     *
     * @return array An associative array of normalized and validated headers.
     *
     * @throws InvalidArgumentException If a header name is not a string,
     *                                  if a header value is not a string,
     *                                  if a header name or value is invalid,
     *                                  or if there are duplicate headers.
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
     * Validates a header name to ensure it meets the required format.
     *
     * @param string $name The header name to validate.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the header name is empty or contains invalid characters.
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
     * Validates the provided header value to ensure it does not contain invalid characters.
     *
     * @param string $value The header value to validate.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the header value contains CR or LF characters.
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
     * Normalizes the header name to a consistent format.
     * Converts each part of the header name, separated by hyphens, to have an initial uppercase letter while the rest are lowercase.
     *
     * @param string $name The header name to be normalized.
     *
     * @return string The normalized header name.
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