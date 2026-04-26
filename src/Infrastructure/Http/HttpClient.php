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

use Mp3StreamTitle\Infrastructure\Http\Request\HttpRequest;
use Mp3StreamTitle\Infrastructure\Http\Request\HttpRequestSerializer;
use Throwable;

final class HttpClient
{
    private SocketConnection $socket;

    public function __construct(SocketConnection $socket)
    {
        $this->socket = $socket;
    }

    /**
     * @throws Throwable
     */
    public function send(HttpRequest $httpRequest): array
    {
        $findHeaders = true;
        $buffer = '';
        $result = [];

        $this->socket->open();

        $serializer = new HttpRequestSerializer();
        $httpRequestString = $serializer->toString($httpRequest);

        $this->socket->write($httpRequestString);

        while ($findHeaders) {
            $buffer .= $this->socket->read();

            if (str_contains($buffer, "\r\n\r\n")) {
                $result['headers'] = substr($buffer, 0, strpos($buffer, "\r\n\r\n") + 4);
                $result['body'] = substr($buffer, strpos($buffer, "\r\n\r\n") + 4);
                $findHeaders = false;
            }
        }

        return $result;
    }
}