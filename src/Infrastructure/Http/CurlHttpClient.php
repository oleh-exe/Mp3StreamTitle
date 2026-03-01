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

use Closure;

readonly class CurlHttpClient
{

    /**
     * @var CurlHttpClientConfig
     */
    private CurlHttpClientConfig $config;

    /**
     * @var array|null
     */
    private ?array $headers;

    /**
     * @var int|null
     */
    private ?int $seconds;

    public function __construct(CurlHttpClientConfig $config)
    {
        $this->config = $config;
    }

    public function getStream(string $streamingUrl, Closure $callback): void
    {
        // Initialize the cURL session.
        $ch = curl_init();

        $headers = $this->headers ?? ['icy-metadata: 1'];
        $timeout = $this->seconds ?? $this->config->timeout;

        // Set the parameters for the session.
        curl_setopt($ch, CURLOPT_URL, $streamingUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config->verifyPeer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->config->verifyHost);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->config->userAgent);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);

        // Execute the request.
        curl_exec($ch);

        // If there are errors we save them into variables.
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        // End the session.
        curl_close($ch);
    }

    /**
     * @param array|null $headers
     * @return void
     */
    public function setHeaders(?array $headers = null): void
    {
        $this->headers = $headers;
    }

    /**
     * @param int|null $seconds
     * @return void
     */
    public function setTimeout(?int $seconds = null): void
    {
        $this->seconds = $seconds;
    }

    /**
     * @param $ch
     * @return void
     */
    public function close($ch): void
    {
        // End the session.
        curl_close($ch);
    }
}