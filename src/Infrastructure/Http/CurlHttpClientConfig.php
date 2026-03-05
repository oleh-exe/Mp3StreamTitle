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

namespace Mp3StreamTitle\Infrastructure\Http;

use InvalidArgumentException;

final readonly class CurlHttpClientConfig
{
    /**
     * @var string
     */
    public string $userAgent;

    /**
     * @var array
     */
    public array $headers;

    /**
     * @var int
     */
    public int $timeout;

    /**
     * @var int
     */
    public int $connectTimeout;

    /**
     * @var bool
     */
    public bool $verifyPeer;

    /**
     * @var int
     */
    public int $verifyHost;

    /**
     * @param string $userAgent
     * @param array $headers
     * @param int $timeout
     * @param int $connectTimeout
     * @param bool $verifyPeer
     * @param int $verifyHost
     */
    public function __construct(
        string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        array $headers = ['Icy-MetaData: 1'],
        int $timeout = 30,
        int $connectTimeout = 10,
        bool $verifyPeer = true,
        int $verifyHost = 2,
    ) {
        if ($userAgent === '') {
            throw new InvalidArgumentException('User-Agent cannot be empty');
        }

        if (empty($headers)) {
            throw new InvalidArgumentException('The header array cannot be empty');
        }

        if ($timeout <= 0) {
            throw new InvalidArgumentException('Timeout must be greater than 0 seconds');
        }

        if ($connectTimeout <= 0) {
            throw new InvalidArgumentException('Connection timeout must be greater than 0 seconds');
        }

        if (!in_array($verifyHost, [0, 2], true)) {
            throw new InvalidArgumentException('verifyHost must be 0 or 2');
        }

        $this->userAgent = $userAgent;
        $this->headers = $headers;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->verifyPeer = $verifyPeer;
        $this->verifyHost = $verifyHost;
    }
}