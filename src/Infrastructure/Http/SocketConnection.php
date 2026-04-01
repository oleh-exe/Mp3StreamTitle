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

final class SocketConnection
{
    /**
     * @var string
     */
    private string $host;

    /**
     * @var int
     */
    private int $port;

    /**
     * @var string
     */
    private string $transport;

    /**
     * @var int
     */
    private int $timeout;

    /**
     * @var mixed
     */
    private mixed $fp;

    /**
     * @param string $host
     * @param int $port
     * @param string $transport
     * @param int $timeout
     */
    public function __construct(
        string $host,
        int $port,
        string $transport,
        int $timeout
    ) {
        if ($timeout <= 0) {
            throw new InvalidArgumentException(
                'Timeout must be greater than 0 seconds'
            );
        }

        $this->host = $host;
        $this->port = $port;
        $this->transport = $transport;
        $this->timeout = $timeout;
    }

    /**
     * @return mixed
     */
    public function open(): mixed
    {
        $remoteAddress = sprintf('%s://%s', $this->transport, $this->host);

        $fp = fsockopen(
            $remoteAddress,
            $this->port,
            $errno,
            $errstr,
            $this->timeout
        );

        if ($fp === false) {
            $errorMessage = sprintf(
                'An error occurred while using sockets. %s (%d)',
                $errstr,
                $errno
            );

            throw new SocketConnectionException($errorMessage);
        }

        $this->fp = $fp;

        return $fp;
    }

    /**
     * @param string $headers
     *
     * @return void
     */
    public function write(string $headers): void
    {
        if (fwrite($this->fp, $headers) === false) {
            throw new SocketConnectionException(
                'Failed to get server response'
            );
        }
    }

    public function read(int $length)
    {
    }

    /**
     * @return void
     */
    public function close(): void
    {
        fclose($this->fp);
    }
}