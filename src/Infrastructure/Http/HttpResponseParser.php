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
     * Parses the provided HTTP response string into an HttpResponse object.
     *
     * @param string $httpResponse The raw HTTP response string to parse, including headers and body.
     *
     * @return HttpResponse The parsed HttpResponse object containing the protocol version, status code,
     * reason phrase, headers, and body.
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
     * Identifies the position and length of the header-body separator in an HTTP response.
     *
     * @param string $httpResponse The full HTTP response as a string.
     *
     * @return array{pos: int, length: int} An associative array containing:
     *                                      - 'pos': The position of the separator in the response.
     *                                      - 'length': The length of the separator pattern.
     *
     * @throws RuntimeException If the header-body separator cannot be found in the provided HTTP response.
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
     * Parses an array of HTTP header lines into a status array and a header array.
     *
     * @param array $lines An array of strings representing the HTTP header lines.
     *
     * @return array{0: array{version: string, code: int, reason: string}, 1: array<string, string>}
     *         Returns a two-element array where:
     *         - The first element is an associative array containing the HTTP version, status code, and reason phrase.
     *         - The second element is an associative array of normalized header names and their corresponding values.
     *
     * @throws RuntimeException If the status line is not found or cannot be parsed.
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
     * Parses the HTTP status line from a response to extract its components.
     *
     * @param string $statusLine The HTTP status line to be parsed.
     *
     * @return array An associative array containing the parsed components:
     *               - 'version': string, the HTTP protocol version (e.g., "1.1").
     *               - 'code': int, the HTTP status code (e.g., 200).
     *               - 'reason': string, the reason phrase (e.g., "OK").
     *
     * @throws RuntimeException If the status line cannot be parsed.
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
     * Normalizes the header name by capitalizing the first letter of each segment
     * separated by a hyphen and converting the remaining letters to lowercase.
     *
     * @param string $name The header name to be normalized.
     *
     * @return string The normalized header name, with each segment properly capitalized.
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