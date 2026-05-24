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

use Mp3StreamTitle\Infrastructure\Http\Request\HeaderCollection;

final readonly class HttpResponse
{
    /**
     * Constructs a new instance of the class.
     *
     * @param string $protocolVersion The HTTP protocol version.
     * @param int $statusCode The HTTP status code.
     * @param string $reason The reason phrase associated with the status code.
     * @param HeaderCollection $headers The collection of headers.
     * @param string $body The body of the HTTP message.
     *
     * @return void
     */
    public function __construct(
        public string $protocolVersion,
        public int $statusCode,
        public string $reason,
        public HeaderCollection $headers,
        public string $body,
    ) {}
}