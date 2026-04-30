<?php
/**
 * Copyright 2020-2026 Oleh Kovalenko
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

namespace Mp3StreamTitle;

use Mp3StreamTitle\Application\Config\Mp3StreamTitleConfig;
use Mp3StreamTitle\Domain\ValueObject\StreamEndpoint;
use Mp3StreamTitle\Infrastructure\Http\CurlHttpClient;
use Mp3StreamTitle\Infrastructure\Http\CurlHttpClientConfig;
use Mp3StreamTitle\Infrastructure\Http\HttpClient;
use Mp3StreamTitle\Infrastructure\Http\IcyMetadataStreamParser;
use Mp3StreamTitle\Infrastructure\Http\MetadataExtractor;
use Mp3StreamTitle\Infrastructure\Http\OffsetResolver;
use Mp3StreamTitle\Infrastructure\Http\Request\HeaderCollection;
use Mp3StreamTitle\Infrastructure\Http\Request\StreamRequestFactory;
use Mp3StreamTitle\Infrastructure\Http\StreamReader;
use RuntimeException;
use Throwable;

final class Mp3StreamTitle
{
    /**
     * Method sendCurl.
     *
     * @var int
     */
    public const SEND_CURL = 1;

    /**
     * Method sendSocket.
     *
     * @var int
     */
    public const SEND_SOCKET = 2;

    /**
     * Method sendFGC.
     *
     * @var int
     */
    public const SEND_FGC = 3;

    /**
     * Configuration settings for the application.
     *
     * @var Mp3StreamTitleConfig|null
     */
    private ?Mp3StreamTitleConfig $config;

    /**
     * Constructor to initialize the Mp3StreamTitle class with a configuration object.
     * If no configuration object is provided, a default instance of Mp3StreamTitleConfig is created.
     *
     * @param Mp3StreamTitleConfig|null $config The configuration object for Mp3StreamTitle. Defaults to null.
     * @return void
     */
    public function __construct(?Mp3StreamTitleConfig $config = null)
    {
        $this->config = $config ?? new Mp3StreamTitleConfig();
    }

    /**
     * The function takes as an argument a direct link to the stream of
     * any online radio station and uses the function specified in the
     * settings to send requests to the stream-server.
     *
     * @param string $streamingUrl
     *
     * @return string|int
     *
     * @throws Throwable
     */
    public function sendRequest(string $streamingUrl): string|int
    {
        return match ($this->config->sendType) {
            // Use the cURL-function.
            self::SEND_CURL => $this->sendCurl($streamingUrl),
            // Use the Socket-function.
            self::SEND_SOCKET => $this->sendSocket($streamingUrl),
            // Use the FGC-function.
            self::SEND_FGC => $this->sendFGC($streamingUrl),
            // TODO: Finalize
            //default => $this->error('error.invalid_send_type'),
        };
    }

    /**
     * The cURL-function takes as an argument a direct link to the stream
     * of the online radio station and sends a cURL request to the stream
     * server. As a result, the function returns information about the song
     * in the following format "artist name and song name".
     *
     * @param string $streamingUrl A direct URL to the online radio stream.
     *
     * @return string Metadata containing song information.
     *
     * @throws RuntimeException If cURL is unavailable or metadata cannot be retrieved.
     */
    private function sendCurl(string $streamingUrl): string
    {
        // Checking if we can use cURL.
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            throw new RuntimeException(
                'The ext-curl extension is required to use Mp3StreamTitle'
            );
        }

        $endpoint = StreamEndpoint::fromString($streamingUrl);

        $offsetResolver = new OffsetResolver();
        // Find out from which byte the metadata will begin
        $offset = $offsetResolver->resolve($endpoint->getUrl(), $this->config);

        $parser = new IcyMetadataStreamParser(
            $offset,
            $this->config->metaMaxLength
        );

        /* The callback-function returns the number of data bytes received or metadata.
           The function is used as the value of the parameter "CURLOPT_WRITEFUNCTION". */
        $callback = function (string $chunk) use ($parser): bool {
            $isComplete = $parser->append($chunk);

            return !$isComplete;
        };

        $curlClient = new CurlHttpClient(
            new CurlHttpClientConfig(
                $this->config->userAgent,
            )
        );

        $curlClient->getStream($endpoint->getUrl(), $callback);

        $metadata = $parser->getMetadata();

        if ($metadata === null) {
            throw new RuntimeException(
                'Failed to extract ICY metadata from the stream'
            );
        }

        // Return the result of the request.
        return $this->getSongInfo($metadata);
    }

    /**
     * The socket-function takes as an argument a direct link to the stream
     * of the online radio station and sends an HTTP request to the stream
     * server. As a result, the function returns information about the song
     * in the following format "artist name and song name".
     *
     * @param string $streamingUrl
     *
     * @return string
     *
     * @throws RuntimeException|Throwable
     */
    private function sendSocket(string $streamingUrl): string
    {
        $endpoint = StreamEndpoint::fromString($streamingUrl);

        $offsetResolver = new OffsetResolver();
        // Find out from which byte the metadata will begin
        $offset = $offsetResolver->resolve($endpoint->getUrl(), $this->config);

        $streamRequest = new StreamRequestFactory();
        /*
        $headerCollection = new HeaderCollection([
            'User-Agent' => $this->config->userAgent
        ]);
        */

        $httpRequest = $streamRequest->create($endpoint, $this->config);

        //$httpClient = new HttpClient();
        //$response = $httpClient->send($endpoint, $httpRequest);
        $streamReader = new StreamReader();

        // Find out how many bytes of data need to be received.
        //$length = $offset + 1 + $this->config->metaMaxLength;
        $metadataBlock = $streamReader->read($endpoint, $httpRequest, $offset, $this->config);

        $extractor = new MetadataExtractor();
        $metadata = $extractor->extract($metadataBlock);

        // Return the result of the request.
        return $this->getSongInfo($metadata);
    }

    /**
     * The FGC-function takes as an argument a direct link to an online
     * radio station stream and opens the stream using the set HTTP headers.
     * As a result, the function returns information about the song
     * in the following format "artist name and song name".
     *
     * @param string $streamingUrl
     * @return string|int
     */
    private function sendFGC(string $streamingUrl): string|int
    {
        $endpoint = StreamEndpoint::fromString($streamingUrl);

        $offsetResolver = new OffsetResolver();
        // Find out from which byte the metadata will begin
        $offset = $offsetResolver->resolve($endpoint->getUrl(), $this->config);

        // HTTP-request headers.
        $optionsMethod = "GET";
        $optionsHeader = "User-Agent: " . $this->config->userAgent . "\r\n";
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
        // Find out how many bytes of data need to be received.
        $dataByte = $offset + 1 + $this->config->metaMaxLength;
        // Open the stream using the HTTP headers set above.
        $buffer = file_get_contents($endpoint->getUrl(), false, $context, 0, $dataByte);

        if ($buffer === false) {
            throw new RuntimeException(
                'Failed to get server response'
            );
        }
        // Find out length of metadata.
        $metaLength = ord(substr($buffer, $offset, 1)) * 16;
        // Get metadata in the following format "StreamTitle='artist name and song name';".
        $metadata = substr($buffer, $offset, $metaLength);

        return $this->getSongInfo($metadata);
    }

    /**
     * The function takes metadata as an argument in the following
     * format "StreamTitle='artist name and song name';" and returns
     * the song information from the metadata in the following format
     * "artist name and song name".
     *
     * @param string $metadata
     * @return string
     */
    private function getSongInfo(string $metadata): string
    {
        /* Find the position of the string "='" indicating the beginning of information about the
           song and find position of the string "';" which indicates the end of the song information. */
        if (($infoStart = strpos($metadata, '=\'')) && ($infoEnd = strpos($metadata, '\';'))) {
            // Get information about the song in the following format "artist name and song name".
            $result = substr($metadata, $infoStart + 2, $infoEnd - ($infoStart + 2));
            // If error messages display disabled.
        } else {
            throw new RuntimeException(
                'Failed to get song info'
            );
        }
        return $result;
    }
}
