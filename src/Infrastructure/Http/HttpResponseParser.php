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

use RuntimeException;

final readonly class HttpResponseParser
{
    public function __construct(
        string $httpResponse
    ) {
        $headerBodySeparator = $this->findHeaderBodySeparator($httpResponse);
        $headers = $this->getHeaders($httpResponse, $headerBodySeparator);
        $body = $this->getBody($httpResponse, $headerBodySeparator);

        return new HttpResponse(
            status: $status,
            headers: $headers,
            body: $body,
        );
    }

    private function findHeaderBodySeparator(string $httpResponse): array
    {
        $result = array();

        $pos = strpos($httpResponse, "\r\n\r\n");
        if ($pos !== false) {
            $length = 4;
            $result = [
                'pos' => $pos,
                'length' => $length
            ];
        }

        // Less strict options
        $pos = strpos($httpResponse, "\n\n");
        if ($pos !== false) {
            $length = 2;
            $result = [
                'pos' => $pos,
                'length' => $length
            ];
        }

        // Mixed cases (rare, but they do happen)
        $pos = strpos($httpResponse, "\r\n\n");
        if ($pos !== false) {
            $length = 3;
            $result = [
                'pos' => $pos,
                'length' => $length
            ];
        }

        if ($result === []) {
            throw new RuntimeException(
                'Could not find the header body separator in the HTTP response'
            );
        }

        return $result;
    }

    private function getHeaders($httpResponse, $headerBodySeparator): array
    {
        $length = $headerBodySeparator['pos'];

        $rawHeaders = substr($httpResponse, 0, $length);

        return explode("\r\n", $rawHeaders);
    }

    private function getBody($httpResponse, $headerBodySeparator): string
    {
        $offset = $headerBodySeparator['pos'] + $headerBodySeparator['length'];
        // Separate the "body" from the headers.
        return substr($httpResponse, $offset);
    }
}