<?php

/**
 * Copyright 2020-2026 Oleh Kovalenko
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Mp3StreamTitle;

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
    private Mp3StreamTitleConfig $config;

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
     * @return string|int
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

            default => $this->error('error.invalid_send_type'),
        };
    }

    /**
     * The cURL-function takes as an argument a direct link to the stream
     * of the online radio station and sends a cURL request to the stream
     * server. As a result, the function returns information about the song
     * in the following format "artist name and song name".
     *
     * @param string $streamingUrl A direct URL to the online radio stream.
     * @return string|int Metadata containing song information or an error code/message.
     * @throws \RuntimeException If the ext-curl extension is not installed or cURL functions are unavailable.
     */
    private function sendCurl(string $streamingUrl): string|int
    {
        // Initialize variables.
        $metadata = '';

        // Checking if we can use cURL.
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            throw new \RuntimeException(
                'The ext-curl extension is required to use Mp3StreamTitle'
            );
        }

        /* Find out from which byte the metadata will begin.
           If successful, continue to perform the function. */
        $offset = $this->getOffset($streamingUrl);

        if (!$offset) {
            throw new \RuntimeException(
                'Failed to get headers from server response to HTTP-request or "icy-metaint" header value'
            );
        } else {
            // Find out how many bytes of data need to get.
            $dataByte = $offset + $this->metaMaxLength;

            /* The callback-function returns the number of data bytes received or metadata.
               The function is used as the value of the parameter "CURLOPT_WRITEFUNCTION". */
            $writeFunction = function ($ch, $chunk) use ($dataByte, $offset, &$metadata) {
                // Initialize variables.
                static $data = '';

                // Find out the length of the data.
                $dataLength = strlen($data) + strlen($chunk);

                // If the length of the received data is greater than or equal to the desired length.
                if ($dataLength >= $dataByte) {
                    // Save the data part into a variable.
                    $data .= substr($chunk, 0, $dataByte - strlen($data));

                    // Find out the length of the metadata.
                    $metaLength = ord(substr($data, $offset, 1)) * 16;

                    // Get metadata in the following format "StreamTitle='artist name and song name';".
                    $metadata = substr($data, $offset, $metaLength);

                    // Interrupt receiving data (with an error "curl_errno: 23").
                    return -1;
                }

                // Save the data part into a variable.
                $data .= $chunk;

                // Return the number of received data bytes.
                return strlen($chunk);
            };

            // Initialize the cURL session.
            $ch = curl_init();

            // Set the parameters for the session.
            curl_setopt($ch, CURLOPT_URL, $streamingUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['icy-metadata: 1']);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->config->userAgent);
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, $writeFunction);

            // Execute the request.
            $tmp = curl_exec($ch);

            // If there are errors we save them into variables.
            $errno = curl_errno($ch);
            $error = curl_error($ch);

            // End the session.
            curl_close($ch);

            // Return the result of the request.
            if ($metadata) {
                $result = $this->getSongInfo($metadata);
                // If error messages display disabled.
            } elseif ($this->showErrors == 0) {
                $result = 0;
                // If enabled.
            } else {
                $result = $error . ' (' . $errno . ').';
            }
            // If error messages display disabled.
        }

        return $result;
    }

    /**
     * The socket-function takes as an argument a direct link to the stream
     * of the online radio station and sends an HTTP-request to the stream
     * server. As a result, the function returns information about the song
     * in the following format "artist name and song name".
     *
     * @param string $streamingUrl
     * @return string|int
     */
    private function sendSocket(string $streamingUrl): string|int
    {
        // Initialize variables.
        $prefix = '';
        $port = 80;
        $path = '/';

        /* Find out from which byte the metadata will begin.
           If successful, continue to perform the function. */
        if ($offset = $this->getOffset($streamingUrl)) {
            // Parse URL.
            $urlPart = parse_url($streamingUrl);

            // Find out protocol.
            if ($urlPart['scheme'] == 'https') {
                $prefix = 'ssl://'; // If HTTPS, use the SSL protocol.
                $port = 443; // If HTTPS, the port can only be 443.
            }

            // Find out port and protocol.
            if (!empty($urlPart['port']) && $urlPart['scheme'] == 'http') {
                $port = $urlPart['port']; // If the HTTP protocol, then the port is non-standard.
            }

            // Find out path.
            if (!empty($urlPart['path'])) {
                $path = $urlPart['path'];
            }

            // Open connection.
            if ($fp = fsockopen($prefix . $urlPart['host'], $port, $errno, $errstr, 30)) {
                // HTTP-request headers.
                $headers = "GET " . $path . " HTTP/1.0\r\n";
                $headers .= "User-Agent: " . $this->config->userAgent . "\r\n";
                $headers .= "icy-metadata: 1\r\n\r\n";

                // Send a request to the stream-server.
                if (fwrite($fp, $headers)) {
                    // Find out how many bytes of data need to be received.
                    $dataByte = $offset + $this->metaMaxLength;

                    // Save the data part into the variable.
                    $buffer = stream_get_contents($fp, $dataByte);

                    // Close the connection.
                    fclose($fp);

                    // Separate the headers from the "body".
                    list($tmp, $body) = explode("\r\n\r\n", $buffer, 2);

                    // Find out length of metadata.
                    $metaLength = ord(substr($body, $offset, 1)) * 16;

                    // Get metadata in the following format "StreamTitle='artist name and song name';".
                    $metadata = substr($body, $offset, $metaLength);

                    // Return the result of the request.
                    $result = $this->getSongInfo($metadata);
                    // If error messages display disabled.
                } elseif ($this->showErrors == 0) {
                    // Close the connection.
                    fclose($fp);

                    $result = 0;
                    // If enabled.
                } else {
                    // Close the connection.
                    fclose($fp);

                    $result = 'Failed to get server response.';
                }
                // If error messages display disabled.
            } elseif ($this->showErrors == 0) {
                $result = 0;
                // If enabled.
            } else {
                $result = 'An error occurred while using sockets. ' . $errstr . ' (' . $errno . ').';
            }
            // If error messages display disabled.
        } elseif ($this->showErrors == 0) {
            $result = 0;
            // If enabled.
        } else {
            $result = 'Failed to get headers from server response to HTTP-request or "icy-metaint" header value.';
        }
        return $result;
    }

    /**
     * The FGC-function takes as an argument a direct link to an online
     * radio station stream and opens the stream using the set HTTP-headers.
     * As a result, the function returns information about the song
     * in the following format "artist name and song name".
     *
     * @param string $streamingUrl
     * @return string|int
     */
    private function sendFGC(string $streamingUrl): string|int
    {
        /* Find out from which byte the metadata will begin.
           If successful, continue to perform the function. */
        if ($offset = $this->getOffset($streamingUrl)) {
            // HTTP-request headers.
            $optionsMethod = "GET";
            $optionsHeader = "User-Agent: " . $this->config->userAgent . "\r\n";
            $optionsHeader .= "icy-metadata: 1\r\n\r\n";

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
            $dataByte = $offset + $this->metaMaxLength;

            // Open the stream using the HTTP-headers set above.
            if ($buffer = file_get_contents($streamingUrl, false, $context, 0, $dataByte)) {
                // Find out length of metadata.
                $metaLength = ord(substr($buffer, $offset, 1)) * 16;

                // Get metadata in the following format "StreamTitle='artist name and song name';".
                $metadata = substr($buffer, $offset, $metaLength);

                // Return the execution result of the function.
                $result = $this->getSongInfo($metadata);
                // If error messages display disabled.
            } elseif ($this->showErrors == 0) {
                $result = 0;
                // If enabled.
            } else {
                $result = 'Failed to get server response.';
            }
            // If error messages display disabled.
        } elseif ($this->showErrors == 0) {
            $result = 0;
            // If enabled.
        } else {
            $result = 'Failed to get headers from server response to HTTP-request or "icy-metaint" header value.';
        }
        return $result;
    }

    /**
     * The function takes metadata as an argument in the following
     * format "StreamTitle='artist name and song name';" and returns
     * the song information from the metadata in the following format
     * "artist name and song name".
     *
     * @param string $metadata
     * @return string|int
     */
    private function getSongInfo(string $metadata): string|int
    {
        /* Find the position of the string "='" indicating the beginning of information about the
           song and find position of the string "';" which indicates the end of the song information. */
        if (($infoStart = strpos($metadata, '=\'')) && ($infoEnd = strpos($metadata, '\';'))) {
            // Get information about the song in the following format "artist name and song name".
            $result = substr($metadata, $infoStart + 2, $infoEnd - ($infoStart + 2));
            // If error messages display disabled.
        } elseif ($this->showErrors == 0) {
            $result = 0;
            // If enabled.
        } else {
            $result = 'Failed to get song info.';
        }
        return $result;
    }

    /**
     * The function takes as an argument a direct link to the stream of the
     * online radio station and sends an HTTP-request to the stream
     * server. In the server response headers, the function looks for the
     * "icy-metaint" header and returns its value.
     *
     * @param string $streamingUrl
     * @return int
     */
    private function getOffset(string $streamingUrl): int
    {
        $result = 0;

        // HTTP-request headers.
        $optionsMethod = "GET";
        $optionsHeader = "User-Agent: " . $this->config->userAgent . "\r\n";
        $optionsHeader .= "icy-metadata: 1\r\n\r\n";

        $options = [
            'http' => [
                'method' => $optionsMethod,
                'header' => $optionsHeader,
                'timeout' => 30
            ]
        ];

        // Create a thread context.
        $context = stream_context_create($options);

        // Get the headers from the server response to the HTTP-request.
        if ($headers = get_headers($streamingUrl, true, $context)) {
            // Looking for the header "icy-metaint".
            if (isset($headers['icy-metaint'])) {
                $value = $headers['icy-metaint'];
                /* Find out how many bytes of data from the stream you need to read before
                   the metadata begins (which contains the name of the artist and the name of the song). */
                $result = is_array($value) ? end($value) : $value;
                $result = intval($result);
            }
        }
        return $result;
    }
}
