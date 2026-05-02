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
        $lines = preg_split('/\r\n|\n|\r/', $rawHeaders) ?: [];

        [$status, $headers] = $this->parseHeaderLines($lines);
        $headers = new HeaderCollection($headers);

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
     * Header/body separator (tolerant framing)
     *
     * @param string $httpResponse
     *
     * @return array
     */
    private function findHeaderBodySeparator(string $httpResponse): array
    {
        $bestPos = null;
        $bestLength = null;
        // Tolerant parser for malformed HTTP-like streams
        $patterns = [
            "\r\n\r\n" => 4,
            "\n\n" => 2,
            "\r\n\n" => 3,
        ];

        foreach ($patterns as $pattern => $length) {
            $pos = strpos($httpResponse, $pattern);

            if ($pos === false) {
                continue;
            }

            if (
                ($bestPos === null)
                || ($pos < $bestPos)
            ) {
                $bestPos = $pos;
                $bestLength = $length;
            }
        }

        if ($bestPos === null) {
            throw new RuntimeException(
                'Could not find the header body separator in the HTTP response'
            );
        }

        return [
            'pos' => $bestPos,
            'length' => $bestLength,
        ];
    }

    /**
     * Single-pass parsing: status + headers
     *
     * @param array $lines
     *
     * @return array{
     *     0: array{
     *     version: string,
     *     code: int,
     *     reason: string
     *     },
     *     1: array<string, string>
     * }
     */
    private function parseHeaderLines(array $lines): array
    {
        $status = null;
        $headers = array();

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // 1. Status line (find once)
            if (
                ($status === null)
                && str_starts_with($line, 'HTTP/')
            ) {
                $status = $this->parseStatusLine($line);
                continue;
            }

            // 2. Header lines
            if (!str_contains($line, ':')) {
                // tolerant mode
                continue;
            }

            [$name, $value] = explode(':', $line, 2);

            $name = trim($name);
            $value = trim($value);
            // --- light filtering (NOT strict validation) ---
            if ($name === '') {
                continue;
            }
            // --- light filtering (NOT strict validation) ---
            if (str_contains($value, "\r") || str_contains($value, "\n")) {
                continue;
            }

            $normalizedName = $this->normalizeHeaderName($name);
            // Option: the last value wins
            $headers[$normalizedName] = $value;
        }

        if ($status === null) {
            throw new RuntimeException('Status line not found');
        }

        return [$status, $headers];
    }

    /**
     * @param string $statusLine
     *
     * @return array{version: string, code: int, reason: string}
     */
    private function parseStatusLine(string $statusLine): array
    {
        $statusLine = preg_replace('/\s+/', ' ', $statusLine) ?? $statusLine;

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