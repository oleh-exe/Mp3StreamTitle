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
     * @var int
     */
    public int $timeout;

    /**
     * @var bool
     */
    public bool $verifyPeer;

    /**
     * @var bool
     */
    public bool $verifyHost;

    /**
     * @param string $userAgent
     * @param int $timeout
     * @param bool $verifyPeer
     * @param bool $verifyHost
     */
    public function __construct(
        string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        int $timeout = 30,
        bool $verifyPeer = true,
        bool $verifyHost = true,
    ) {
        if ($userAgent === '') {
            throw new InvalidArgumentException('User-Agent cannot be empty');
        }

        $this->userAgent = $userAgent;
        $this->timeout = $timeout;
        $this->verifyPeer = $verifyPeer;
        $this->verifyHost = $verifyHost;
    }
}