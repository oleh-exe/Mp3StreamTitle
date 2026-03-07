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

readonly class CurlHttpClient
{
    /**
     * @var CurlHttpClientConfig
     */
    private CurlHttpClientConfig $config;

    /**
     * @param CurlHttpClientConfig $config
     */
    public function __construct(CurlHttpClientConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Streams data from the provided URL and processes it using the callback function.
     *
     * @param string $streamingUrl The URL to stream data from. Must not be empty.
     * @param callable $callback A callback function that processes each chunk of streamed data.
     *                            The callback should return false to interrupt the streaming process.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the provided URL is empty.
     * @throws CurlHttpException If an error occurs during the cURL session or there is an HTTP error.
     */
    public function getStream(string $streamingUrl, callable $callback): void
    {
        if ($streamingUrl === '') {
            throw new InvalidArgumentException('URL cannot be empty');
        }

        // Initialize the cURL session.
        $ch = curl_init();

        if ($ch === false) {
            throw new CurlHttpException('Failed to initialize cURL');
        }

        $manuallyInterrupted = false;

        // Set the parameters for the session.
        curl_setopt_array($ch, [
            CURLOPT_URL => $streamingUrl,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_SSL_VERIFYPEER => $this->config->verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $this->config->verifyHost,
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout,
            CURLOPT_HTTPHEADER => $this->config->headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => $this->config->userAgent,
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use ($callback, &$manuallyInterrupted): int {
                $shouldContinue = $callback($chunk);

                if ($shouldContinue === false) {
                    $manuallyInterrupted = true;

                    // Interrupt receiving data (with an error "curl_errno: 23").
                    return -1;
                }

                // Return the number of received data bytes.
                return strlen($chunk);
            },
        ]);

        // Execute the request.
        curl_exec($ch);

        // If there are errors we save them into variables.
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // End the session.
        curl_close($ch);

        // If we intentionally interrupted the transfer → not an error
        if ($manuallyInterrupted && $errno === CURLE_WRITE_ERROR) {
            return;
        }

        if ($errno !== 0) {
            throw new CurlHttpException(
                sprintf('cURL error (%d): %s', $errno, $error),
                $errno
            );
        }

        if ($httpCode >= 400) {
            throw new CurlHttpException(
                sprintf('HTTP error: %d', $httpCode),
                $httpCode
            );
        }
    }
}