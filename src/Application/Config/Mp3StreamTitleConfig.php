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

namespace Mp3StreamTitle\Application\Config;

use Mp3StreamTitle\Mp3StreamTitle;

final class Mp3StreamTitleConfig
{
    /**
     * Indicate which function to use to send requests to the stream-server.
     * 1 — cURL-function.
     * 2 — Socket-function.
     * 3 — FGC-function.
     *
     * @var int
     */
    public readonly int $sendType;

    /**
     * The contents of our "User-Agent" HTTP-header.
     *
     * @var string
     */
    public readonly string $userAgent;

    /**
     * Enable or disable the display of error messages.
     * false — Error messages display disabled.
     * true — Error messages display enabled.
     *
     * @var bool
     */
    public readonly bool $showErrors;

    /**
     * Maximum metadata length in bytes.
     *
     * @var int
     */
    public readonly int $metaMaxLength;

    /**
     * Constructor for initializing the Mp3StreamTitle object with specified parameters.
     *
     * @param int $sendType The method used to get the stream title. Defaults to Mp3StreamTitle::SEND_CURL.
     * @param string $userAgent The user agent string for HTTP requests. Cannot be empty.
     * @param bool $showErrors A flag to indicate whether errors should be displayed. Defaults to false.
     * @param int $metaMaxLength The maximum length of metadata in bytes. Must not exceed 4080 bytes.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the user agent is empty or metaMaxLength exceeds 4080 bytes.
     */
    public function __construct(
        int $sendType = Mp3StreamTitle::SEND_CURL,
        string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        bool $showErrors = false,
        int $metaMaxLength = 4080
    ) {
        if ($userAgent === '') {
            throw new \InvalidArgumentException('User-Agent cannot be empty');
        }

        if ($metaMaxLength > 4080) {
            throw new \InvalidArgumentException('metaMaxLength must be no more than 4080 bytes');
        }

        $this->sendType = $sendType;
        $this->userAgent = $userAgent;
        $this->showErrors = $showErrors;
        $this->metaMaxLength = $metaMaxLength;
    }
}