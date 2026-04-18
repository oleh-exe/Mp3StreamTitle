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
    /**
     * @param string $httpResponse
     *
     * @return HttpResponse
     */
    public function parse(string $httpResponse): HttpResponse
    {
        $headerBodySeparator = $this->findHeaderBodySeparator($httpResponse);

        if ($headerBodySeparator === []) {
            throw new RuntimeException(
                'Could not find the header body separator in the HTTP response'
            );
        }

        $status = $this->getStatusCode($httpResponse, $headerBodySeparator);
        $headers = $this->getHeaders($httpResponse, $headerBodySeparator);
        $body = $this->getBody($httpResponse, $headerBodySeparator);

        return new HttpResponse(
            status: $status,
            headers: $headers,
            body: $body,
        );
    }

    /**
     * @param string $httpResponse
     *
     * @return array
     */
    private function findHeaderBodySeparator(string $httpResponse): array
    {
        $pos = strpos($httpResponse, "\r\n\r\n");
        if ($pos !== false) {
            $length = 4;

            return [
                'pos' => $pos,
                'length' => $length
            ];
        }

        // Less strict options
        $pos = strpos($httpResponse, "\n\n");
        if ($pos !== false) {
            $length = 2;

            return [
                'pos' => $pos,
                'length' => $length
            ];
        }

        // Mixed cases (rare, but they do happen)
        $pos = strpos($httpResponse, "\r\n\n");
        if ($pos !== false) {
            $length = 3;

            return [
                'pos' => $pos,
                'length' => $length
            ];
        }

        return [];
    }

    /**
     * @param string $httpResponse
     * @param array $headerBodySeparator
     *
     * @return int
     */
    private function getStatusCode(string $httpResponse, array $headerBodySeparator): int
    {
        $length = $headerBodySeparator['pos'];

        $rawHeaders = substr($httpResponse, 0, $length);

        [, $statusCode,] = explode(' ', $rawHeaders, 3);

        return intval($statusCode);
    }

    /**
     * @param string $httpResponse
     * @param array $headerBodySeparator
     *
     * @return array
     */
    private function getHeaders(string $httpResponse, array $headerBodySeparator): array
    {
        $length = $headerBodySeparator['pos'];
        $headers = [];

        $rawHeaders = substr($httpResponse, 0, $length);
        // Support \r\n and \n and \r
        $lines = preg_split('/\r\n|\n|\r/', $rawHeaders);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Skipping the status line (HTTP/1.1 200 OK)
            if (str_starts_with($line, 'HTTP/')) {
                continue;
            }

            if (!str_contains($line, ':')) {
                // tolerant mode
                continue;
            }

            [$name, $value] = explode(':', $line, 2);

            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            // Option: the last value wins
            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * @param string $httpResponse
     * @param array $headerBodySeparator
     *
     * @return string
     */
    private function getBody(string $httpResponse, array $headerBodySeparator): string
    {
        $offset = $headerBodySeparator['pos'] + $headerBodySeparator['length'];
        // Separate the "body" from the headers.
        return substr($httpResponse, $offset);
    }
}