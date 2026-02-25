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
     * @var array
     */
    private array $options;

    /**
     * @var array
     */
    private array $headers;

    /**
     * @var int
     */
    private int $seconds;

    /**
     * @var Closure
     */
    private Closure $callback;

    /**
     * @var string
     */
    private string $userAgent;

    /**
     * @var string
     */
    private string $streamingUrl;

    /**
     * @param array $options
     */
    public function __construct(
        array $options = [],
        //string $streamingUrl,
        //string $userAgent,
        //Closure $callback
    )
    {
        //$this->streamingUrl = $streamingUrl;
        //$this->userAgent = $userAgent;
        //$this->callback = $callback;
        $this->options = $options;
    }

    public function getStream(
        string $url,
        callable $onChunk
    ): void {
        // Initialize the cURL session.
        $ch = curl_init();

        // Set the parameters for the session.
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['icy-metadata: 1']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->options['userAgent']);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $onChunk);

        // Execute the request.
        curl_exec($ch);

        // If there are errors we save them into variables.
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        // End the session.
        curl_close($ch);
    }

    /**
     * @param array $headers
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @param int $seconds
     * @return void
     */
    public function setTimeout(int $seconds): void
    {
        $this->seconds = $seconds;
    }

    /**
     * @return void
     */
    public function close(): void
    {
    }
}