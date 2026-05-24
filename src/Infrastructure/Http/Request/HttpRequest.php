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

final readonly class HttpRequest
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
     * @var HeaderCollection
     */
    private HeaderCollection $headers;

    /**
     * Constructor for initializing an HTTP request object.
     *
     * @param HttpMethod $method The HTTP method (e.g., GET) of the request.
     * @param string $target The target URI of the request. Must start with '/'.
     * @param HttpVersion $httpVersion The HTTP version of the request (e.g., 1.0).
     * @param HeaderCollection $headers The collection of headers associated with the request.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the target is empty or does not start with '/'.
     */
    public function __construct(
        HttpMethod $method,
        string $target,
        HttpVersion $httpVersion,
        HeaderCollection $headers,
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
        $this->headers = $headers;
    }

    /**
     * Gets the HTTP method.
     *
     * @return HttpMethod The HTTP method instance.
     */
    public function method(): HttpMethod
    {
        return $this->method;
    }

    /**
     * Retrieves the target value.
     *
     * @return string The target value.
     */
    public function target(): string
    {
        return $this->target;
    }

    /**
     * Retrieves the HTTP version.
     *
     * @return HttpVersion The HTTP version.
     */
    public function version(): HttpVersion
    {
        return $this->httpVersion;
    }

    /**
     * Retrieves the collection of headers.
     *
     * @return HeaderCollection The collection of headers.
     */
    public function headers(): HeaderCollection
    {
        return $this->headers;
    }
}