<?php

/**
 * Copyright 2020-2025 Oleh Kovalenko
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

class Mp3StreamTitle
{
    /**
     * Indicate which function to use to send requests to the stream-server.
     * 1 — cURL-function.
     * 2 — Socket-function.
     * 3 — FGC-function.
     *
     * @var int
     */
    private int $sendType = 1;

    /**
     * The contents of our "User-Agent" HTTP-header.
     *
     * @var string
     */
    private string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36';

    /**
     * Enable or disable the display of error messages.
     * 0 — Error messages display disabled.
     * 1 — Error messages display enabled.
     *
     * @var int
     */
    private int $showErrors = 0;

    /**
     * Maximum metadata length in bytes.
     *
     * @var int
     */
    private int $metaMaxLength = 5228;

    /**
     * The function takes as an argument a direct link to the stream of
     * any online radio station and uses the function specified in the
     * settings to send requests to the stream-server.
     *
     * @param string $streamingUrl
     * @return int|string|Mp3StreamTitle
     */
    public function sendRequest(string $streamingUrl): int|string|static
    {
        // Use the cURL-function.
        if ($this->sendType == 1) {
            return $this->sendCurl($streamingUrl);
            // Use the Socket-function.
        } elseif ($this->sendType == 2) {
            return $this->sendSocket($streamingUrl);
            // Use the FGC-function.
        } else {
            return $this->sendFGC($streamingUrl);
        }
    }

    /**
     * The cURL-function takes as an argument a direct link to the stream
     * of the online radio station and sends a cURL request to the stream
     * server. As a result, the function returns information about the song
     * in the following format "artist name and song name".
     *
     * @param string $streamingUrl
     * @return string|int
     */
    private function sendCurl(string $streamingUrl): string|int
    {
        // Initialize variables.
        $metadata = '';

        // Checking if we can use cURL.
        if (extension_loaded('curl') && function_exists('curl_init')) {
            /* Find out from which byte the metadata will begin.
               If successful, continue to perform the function. */
            if ($offset = $this->getOffset($streamingUrl)) {
                // Find out how many bytes of data you need to get.
                $data_byte = $offset + $this->metaMaxLength;

                /* The callback-function returns the number of data bytes received or metadata.
                   The function is used as the value of the parameter "CURLOPT_WRITEFUNCTION". */
                $write_function = function ($ch, $chunk) use ($data_byte, $offset, &$metadata) {
                    // Initialize variables.
                    static $data = '';

                    // Find out the length of the data.
                    $data_length = strlen($data) + strlen($chunk);

                    // If the length of the received data is greater than or equal to the desired length.
                    if ($data_length >= $data_byte) {
                        // Save the data part into a variable.
                        $data .= substr($chunk, 0, $data_byte - strlen($data));

                        // Find out the length of the metadata.
                        $meta_length = ord(substr($data, $offset, 1)) * 16;

                        // Get metadata in the following format "StreamTitle='artist name and song name';".
                        $metadata = substr($data, $offset, $meta_length);

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
                curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write_function);

                // Execute the request.
                $tmp = @curl_exec($ch);

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
            } elseif ($this->showErrors == 0) {
                $result = 0;
                // If enabled.
            } else {
                $result = 'Failed to get headers from server response to HTTP-request or "icy-metaint" header value.';
            }
            // If error messages display disabled.
        } elseif ($this->showErrors == 0) {
            $result = 0;
            // If enabled.
        } else {
            $result = 'There is no way to use the cURL library on this hosting.';
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
            $url_part = parse_url($streamingUrl);

            // Find out protocol.
            if ($url_part['scheme'] == 'https') {
                $prefix = 'ssl://'; // If HTTPS, use the SSL protocol.
                $port = 443; // If HTTPS, the port can only be 443.
            }

            // Find out port and protocol.
            if (!empty($url_part['port']) && $url_part['scheme'] == 'http') {
                $port = $url_part['port']; // If the HTTP protocol, then the port is non-standard.
            }

            // Find out path.
            if (!empty($url_part['path'])) {
                $path = $url_part['path'];
            }

            // Open connection.
            if ($fp = @fsockopen($prefix . $url_part['host'], $port, $errno, $errstr, 30)) {
                // HTTP-request headers.
                $headers = "GET " . $path . " HTTP/1.0\r\n";
                $headers .= "User-Agent: " . $this->userAgent . "\r\n";
                $headers .= "icy-metadata: 1\r\n\r\n";

                // Send a request to the stream-server.
                if (fwrite($fp, $headers)) {
                    // Find out how many bytes of data need to be received.
                    $data_byte = $offset + $this->metaMaxLength;

                    // Save the data part into the variable.
                    $buffer = stream_get_contents($fp, $data_byte);

                    // Close the connection.
                    fclose($fp);

                    // Separate the headers from the "body".
                    list($tmp, $body) = explode("\r\n\r\n", $buffer, 2);

                    // Find out length of metadata.
                    $meta_length = ord(substr($body, $offset, 1)) * 16;

                    // Get metadata in the following format "StreamTitle='artist name and song name';".
                    $metadata = substr($body, $offset, $meta_length);

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
            $options_method = "GET";
            $options_header = "User-Agent: " . $this->userAgent . "\r\n";
            $options_header .= "icy-metadata: 1\r\n\r\n";

            $options = [
                'http' => [
                    'method' => $options_method,
                    'header' => $options_header,
                    'timeout' => 30
                ]
            ];

            // Create a thread context.
            $context = stream_context_create($options);

            // Find out how many bytes of data need to be received.
            $data_byte = $offset + $this->metaMaxLength;

            // Open the stream using the HTTP-headers set above.
            if ($buffer = @file_get_contents($streamingUrl, false, $context, 0, $data_byte)) {
                // Find out length of metadata.
                $meta_length = ord(substr($buffer, $offset, 1)) * 16;

                // Get metadata in the following format "StreamTitle='artist name and song name';".
                $metadata = substr($buffer, $offset, $meta_length);

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
        if (($info_start = strpos($metadata, '=\'')) && ($info_end = strpos($metadata, '\';'))) {
            // Get information about the song in the following format "artist name and song name".
            $result = substr($metadata, $info_start + 2, $info_end - ($info_start + 2));
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
     * @return string|int
     */
    private function getOffset(string $streamingUrl): string|int
    {
        // Initialize variables.
        $result = 0;

        // HTTP-request headers.
        $options_method = "GET";
        $options_header = "User-Agent: " . $this->userAgent . "\r\n";
        $options_header .= "icy-metadata: 1\r\n\r\n";

        $options = [
            'http' => [
                'method' => $options_method,
                'header' => $options_header,
                'timeout' => 30
            ]
        ];

        // Create a thread context.
        $context = stream_context_create($options);

        // Get the headers from the server response to the HTTP-request.
        if ($headers = @get_headers($streamingUrl, 0, $context)) {
            // Looking for the header "icy-metaint".
            foreach ($headers as $h) {
                /* Find out how many bytes of data from the stream you need to read before
                   the metadata begins (which contains the name of the artist and the name of the song). */
                if (strpos($h, 'icy-metaint') !== false && ($result = explode(':', $h)[1])) {
                    // Break the cycle.
                    break;
                }
            }
        }
        return $result;
    }
}
