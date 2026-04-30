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
use RuntimeException;
use Throwable;

final class StreamReader
{
    /**
     * @throws Throwable
     */
    public function read(
        StreamEndpoint $endpoint,
        HttpRequest $httpRequest,
        int $offset,
        Mp3StreamTitleConfig $config
    ): string {
        $socket = new SocketConnection(
            $endpoint->getHost(),
            $endpoint->getPort(),
            $endpoint->getTransport(),
            30,
        );

        $httpClient = new HttpClient($socket);

        try {
            $httpResponseParts = $httpClient->send($httpRequest);

            $body = $httpResponseParts->body;
            $safetyMargin = 1024;
            $maxAllowed = $offset + 1 + $config->metaMaxLength + $safetyMargin;

            if (strlen($body) < $offset) {
                $length = $offset + 1;
                $this->readUntilLength($body, $length, $socket, $maxAllowed);
            }

            if (!isset($body[$offset])) {
                throw new RuntimeException(
                    'Metadata offset is out of bounds'
                );
            }
            // ICY metadata block structure:
            // [offset]      = length byte (metadata length / 16)
            // [offset + 1]  = start of actual metadata (e.g., StreamTitle='...';)
            $metaStart = $offset + 1;
            // Find out the length of metadata.
            $metaLength = ord($body[$offset]) * 16;

            if ($metaLength === 0) {
                return '';
            }

            $length = $metaStart + $metaLength;

            if (strlen($body) < $length) {
                $this->readUntilLength($body, $length, $socket, $maxAllowed);
            }
        } finally {
            $socket->close();
        }

        // Get metadata in the following format "StreamTitle='artist name and song name';".
        return substr(
            $body,
            $metaStart,
            $metaLength
        );
    }

    /**
     * @throws Throwable
     */
    private function readUntilLength(string &$body, int $length, SocketConnection $socket, int $maxAllowed): void
    {
        while (strlen($body) < $length) {
            $body .= $socket->read();

            if (strlen($body) > $maxAllowed) {
                throw new RuntimeException(
                    sprintf('Stream body exceeded maximum allowed length (%d bytes)', $maxAllowed)
                );
            }
        }
    }
}