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
use Mp3StreamTitle\Infrastructure\Http\Enum\HttpVersion;
use ValueError;

final class HttpRequest
{
    /**
     * @var string
     */
    private string $method;

    /**
     * @var array
     */
    private array $headers;

    /**
     * @var string
     */
    private string $target;

    private HttpVersion $httpVersion;

    /**
     * @param array $headers
     * @param string $target
     * @param string $version
     * @param string $method
     */
    public function __construct(
        array $headers = array(),
        string $target = '',
        string $version = '1.1',
        string $method = 'GET'
    ) {
        try {
            $this->httpVersion = HttpVersion::from($version);
        } catch (ValueError) {
            throw new InvalidArgumentException(
                'Invalid HTTP version'
            );
        }

        if (empty($method)) {
            throw new InvalidArgumentException(
                'Method cannot be empty'
            );
        }

        $this->headers = $headers;
        $this->target = $target;
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function method(): string
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
     * @return string
     */
    public function version(): string
    {
        return 'HTTP/' . $this->httpVersion->value;
    }

    /**
     * @return string
     */
    public function headers(): string
    {
        $headers = array();

        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        return implode("\r\n", $headers) . "\r\n\r\n";
    }

    public function toString(): string
    {
        $requestLine = $this->method() . ' ' . $this->target() . ' ' . $this->version() . "\r\n";

        return $requestLine . $this->headers();
    }
}