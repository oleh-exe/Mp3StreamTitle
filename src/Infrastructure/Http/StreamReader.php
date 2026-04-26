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
use Mp3StreamTitle\Domain\ValueObject\StreamEndpoint;
use Mp3StreamTitle\Infrastructure\Http\Request\HttpRequest;
use Throwable;

final class StreamReader
{
    /**
     * @throws Throwable
     */
    public function read(StreamEndpoint $endpoint, HttpRequest $httpRequest, int $offset): HttpResponse
    {
        /*if ($length <= 0) {
            throw new InvalidArgumentException(
                'Length must be greater than 0'
            );
        }*/

        $socket = new SocketConnection(
            $endpoint->getHost(),
            $endpoint->getPort(),
            $endpoint->getTransport(),
            30,
        );

        $httpClient = new HttpClient($socket);

        try {
            $response = $httpClient->send($httpRequest);
            $body = $response['body'];
            $alreadyRead = strlen($body);

            if ($alreadyRead <= $offset) {
                while (strlen($body) <= $offset) {
                    $body .= $socket->read();
                }
            }
        } finally {
            $socket->close();
        }

        $parser = new HttpResponseParser();
        return $parser->parse($response['headers'] . $body);
    }
}