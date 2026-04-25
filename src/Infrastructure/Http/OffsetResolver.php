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
use RuntimeException;

final class OffsetResolver
{
    /**
     * @param string $streamingUrl
     * @param Mp3StreamTitleConfig $config
     * @return int
     */
    public function resolve(string $streamingUrl, Mp3StreamTitleConfig $config): int
    {
        // HTTP-request headers.
        $optionsMethod = "GET";
        $optionsHeader = "User-Agent: " . $config->userAgent . "\r\n";
        $optionsHeader .= "Icy-MetaData: 1\r\n\r\n";

        $options = [
            'http' => [
                'method' => $optionsMethod,
                'header' => $optionsHeader,
                'timeout' => 30
            ]
        ];

        // Create a thread context.
        $context = stream_context_create($options);

        // Get the headers from the server response to the HTTP request.
        $headers = get_headers($streamingUrl, true, $context);

        if ($headers === false) {
            throw new RuntimeException(
                'Failed to get headers from server response to HTTP-request'
            );
        }

        if (!isset($headers['icy-metaint'])) {
            throw new RuntimeException(
                'Failed to get headers from server response to HTTP-request or "icy-metaint" header value'
            );
        }

        // Looking for the header "icy-metaint".
        $value = $headers['icy-metaint'];
        /* Find out how many bytes of data from the stream you need to read before
           the metadata begins (which contains the name of the artist and the name of the song). */
        $result = is_array($value) ? end($value) : $value;
        $result = intval($result);

        if ($result <= 0) {
            throw new RuntimeException(
                'Invalid "icy-metaint" header value'
            );
        }

        return $result;
    }
}