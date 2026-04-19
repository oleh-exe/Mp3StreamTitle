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
        // Separate the headers from the "body".
        $length = $headerBodySeparator['pos'];
        $rawHeaders = substr($httpResponse, 0, $length);
        $headers = preg_split('/\r\n|\n|\r/', $rawHeaders);

        $status = $this->getStatus($headers);
        $headers = $this->getHeaders($headers);
        // Separate the "body" from the headers.
        $offset = $headerBodySeparator['pos'] + $headerBodySeparator['length'];
        $body = substr($httpResponse, $offset);

        return new HttpResponse(
            protocolVersion: $status['version'],
            statusCode: $status['code'],
            reason: $status['reason'],
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
        $candidates = [];

        $patterns = [
            "\r\n\r\n" => 4,
            "\n\n" => 2,
            "\r\n\n" => 3,
        ];

        foreach ($patterns as $pattern => $length) {
            $pos = strpos($httpResponse, $pattern);

            if ($pos !== false) {
                $candidates[] = [
                    'pos' => $pos,
                    'length' => $length,
                ];
            }
        }

        if ($candidates === []) {
            throw new RuntimeException(
                'Could not find the header body separator in the HTTP response'
            );
        }

        // Finding the earliest separator
        usort(
            $candidates,
            static fn(array $a, array $b): int => $a['pos'] <=> $b['pos']
        );

        return $candidates[0];
    }

    /**
     * @param array $headers
     *
     * @return array
     */
    private function getStatus(array $headers): array
    {
        $statusLine = '';

        foreach ($headers as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }
            // Searching for the status line (HTTP/1.1 200 OK)
            if (!str_starts_with($line, 'HTTP/')) {
                continue;
            }

            $statusLine = $line;

            break;
        }

        if ($statusLine === '') {
            throw new RuntimeException(
                'Could not find the status line in the HTTP response'
            );
        }

        $statusLine = preg_replace('/\s+/', ' ', $statusLine);
        // HTTP protocol version <= 1.1
        if (!preg_match(
            '#^HTTP/(\d\.\d)\s+(\d{3})(?:\s+(.*))?$#',
            $statusLine,
            $matches,
            PREG_UNMATCHED_AS_NULL
        )) {
            throw new RuntimeException(
                'Could not parse the status line in the HTTP response'
            );
        }

        return [
            'version' => $matches[1], // 1.1
            'code' => (int) $matches[2], // 200
            'reason' => $matches[3] ?? '', // OK
        ];
    }

    /**
     * @param array $headers
     *
     * @return array
     */
    private function getHeaders(array $headers): array
    {
        $result = array();

        foreach ($headers as $line) {
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

            $this->assertValidHeaderName($name);
            $normalizedName = $this->normalizeHeaderName($name);

            // Option: the last value wins
            $result[$normalizedName] = $value;
        }

        return $result;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    private function assertValidHeaderName(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9!#$%&\'*+\-.^_`|~]+$/', $name)) {
            throw new InvalidArgumentException(
                sprintf('Invalid header name "%s"', $name)
            );
        }
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function normalizeHeaderName(string $name): string
    {
        $splitName = array_map(
            static fn(string $part): string => ucfirst(strtolower($part)),
            explode('-', $name)
        );

        return implode('-', $splitName);
    }
}