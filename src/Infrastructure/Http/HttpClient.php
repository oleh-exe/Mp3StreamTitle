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

use Mp3StreamTitle\Application\Config\Mp3StreamTitleConfig;
use Mp3StreamTitle\Domain\ValueObject\StreamEndpoint;
use Mp3StreamTitle\Infrastructure\Http\Request\HttpRequest;
use Mp3StreamTitle\Infrastructure\Http\Request\HttpRequestSerializer;
use Throwable;

final class HttpClient
{
    /**
     * @param StreamEndpoint $endpoint
     * @param HttpRequest $httpRequest
     * @param int $offset
     * @param Mp3StreamTitleConfig $config
     * @return HttpResponse
     * @throws Throwable
     */
    public function send(StreamEndpoint $endpoint, HttpRequest $httpRequest, int $offset, Mp3StreamTitleConfig $config): HttpResponse
    {
        $socket = new SocketConnection(
            $endpoint->getHost(),
            $endpoint->getPort(),
            $endpoint->getTransport(),
            30,
        );

        $serializer = new HttpRequestSerializer();
        $httpRequestString = $serializer->toString($httpRequest);

        // Find out how many bytes of data need to be received.
        $length = $offset + 1 + $config->metaMaxLength;

        try {
            $socket->open();
            // Send a request to the stream-server
            $socket->write($httpRequestString);
            // Save the data part into the variable.
            $httpResponse = $socket->read($length);
        } finally {
            $socket->close();
        }

        $parser = new HttpResponseParser();
        return $parser->parse($httpResponse);
    }
}