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
use Mp3StreamTitle\Infrastructure\Http\Enum\HttpMethod;
use Mp3StreamTitle\Infrastructure\Http\Enum\HttpVersion;

final readonly class StreamGetRequest
{
    /**
     * @var HttpMethod
     */
    private HttpMethod $method;

    /**
     * @var string
     */
    private string $target;

    /**
     * @var HttpVersion
     */
    private HttpVersion $httpVersion;

    /**
     * @var array
     */
    private array $headers;

    /**
     * @param HttpMethod $method
     * @param string $target
     * @param HttpVersion $httpVersion
     * @param array $headers
     */
    private function __construct(
        HttpMethod $method,
        string $target,
        HttpVersion $httpVersion,
        array $headers,
    ) {
        if (empty($target)) {
            throw new InvalidArgumentException(
                'Target cannot be empty'
            );
        }

        if (!str_starts_with($target, '/')) {
            throw new InvalidArgumentException(
                sprintf('Target must start with "/", got "%s"', $target)
            );
        }

        $this->method = $method;
        $this->target = $target;
        $this->httpVersion = $httpVersion;
        $this->headers = $this->normalizeAndValidateHeaders($headers);
    }

    /**
     * @param HttpMethod $method
     * @param string $target
     * @param HttpVersion $httpVersion
     * @param array $headers
     * @return self
     */
    public static function fromStream(
        HttpMethod $method,
        string $target,
        HttpVersion $httpVersion,
        array $headers = [],
    ): self {
        return new self(
            method: $method,
            target: $target,
            httpVersion: $httpVersion,
            headers: $headers,
        );
    }

    /**
     * @return HttpMethod
     */
    public function method(): HttpMethod
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function target(): string
    {
        return $this->target;
    }

    /**
     * @return HttpVersion
     */
    public function version(): HttpVersion
    {
        return $this->httpVersion;
    }

    /**
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        $requestLine = sprintf(
            '%s %s HTTP/%s' . "\r\n",
            $this->method->value,
            $this->target,
            $this->httpVersion->value
        );

        return $requestLine . $this->serializeHeaders();
    }

    /**
     * @return string
     */
    public function serializeHeaders(): string
    {
        $lines = [];

        foreach ($this->headers as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }

        return implode("\r\n", $lines) . "\r\n\r\n";
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