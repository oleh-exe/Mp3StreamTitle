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
use LogicException;
use Mp3StreamTitle\Domain\ValueObject\Transport;
use Mp3StreamTitle\Exception\Http\SocketConnectionException;
use Mp3StreamTitle\Infrastructure\Http\Enum\ConnectionState;
use Throwable;

final class SocketConnection
{
    /**
     * @var resource|null
     */
    private $fp = null;

    /**
     * The current state of the connection.
     */
    private ConnectionState $state = ConnectionState::INITIAL;

    /**
     * @param string $host
     * @param int $port
     * @param Transport $transport
     * @param int $timeout
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly Transport $transport,
        private readonly int $timeout
    ) {
        if ($timeout <= 0) {
            throw new InvalidArgumentException(
                'Timeout must be greater than 0 seconds'
            );
        }
    }

    /**
     * @throws Throwable
     */
    public function open(): void
    {
        if ($this->state === ConnectionState::ERROR) {
            throw new LogicException(
                'Connection cannot be reused after failure'
            );
        }

        if (
            !in_array($this->state, [ConnectionState::INITIAL, ConnectionState::CLOSED], true)
        ) {
            throw new LogicException(
                sprintf(
                    'Connection cannot be opened from state %s',
                    $this->state->value
                )
            );
        }

        $this->state = ConnectionState::CONNECTING;

        $remoteAddress = sprintf('%s://%s', $this->transport->toSocketScheme(), $this->host);

        $fp = fsockopen(
            $remoteAddress,
            $this->port,
            $errno,
            $errstr,
            $this->timeout
        );

        if ($fp === false) {
            $errorMessage = sprintf(
                'Connection failed: %s (%d)',
                $errstr,
                $errno
            );

            $this->fail(new SocketConnectionException($errorMessage));
        }

        try {
            if (!stream_set_blocking($fp, true)) {
                throw new SocketConnectionException(
                    'Unable to set stream to blocking mode'
                );
            }

            if (!stream_set_timeout($fp, $this->timeout)) {
                throw new SocketConnectionException(
                    'Unable to set stream timeout'
                );
            }

            $this->fp = $fp;
            $this->state = ConnectionState::CONNECTED;
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * @param string $data
     *
     * @return void
     * @throws Throwable
     */
    public function write(string $data): void
    {
        $this->assertConnected();

        $this->state = ConnectionState::WRITING;

        try {
            $length = strlen($data);
            $written = 0;

            while ($written < $length) {
                $chunk = substr($data, $written);
                $chunkLength = strlen($chunk);

                $bytes = fwrite($this->fp, $chunk);

                if ($bytes === false || $bytes === 0) {
                    throw new SocketConnectionException(
                        sprintf(
                            'Socket write failed (attempted %d bytes)',
                            $chunkLength
                        )
                    );
                }

                $written += $bytes;
            }

            $this->state = ConnectionState::CONNECTED;
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * @param int $length
     *
     * @return string
     * @throws Throwable
     */
    public function read(int $length): string
    {
        $this->assertConnected();

        $this->state = ConnectionState::READING;

        try {
            if ($length <= 0) {
                throw new InvalidArgumentException(
                    'Length must be greater than 0'
                );
            }

            $remaining = $length;
            $buffer = '';

            while ($remaining > 0) {
                $chunk = fread($this->fp, $remaining);

                if ($chunk === false) {
                    throw new SocketConnectionException(
                        'Socket read failed'
                    );
                }

                if ($chunk === '') {
                    $meta = stream_get_meta_data($this->fp);

                    if ($meta['timed_out']) {
                        throw new SocketConnectionException(
                            'Read timeout'
                        );
                    }

                    if ($meta['eof']) {
                        throw new SocketConnectionException(
                            'Unexpected EOF'
                        );
                    }

                    throw new SocketConnectionException(
                        'Empty read without EOF or timeout'
                    );
                }

                $chunkLength = strlen($chunk);

                $buffer .= $chunk;
                $remaining -= $chunkLength;
            }

            $this->state = ConnectionState::CONNECTED;

            return $buffer;
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }

        $this->fp = null;

        if ($this->state !== ConnectionState::ERROR) {
            $this->state = ConnectionState::CLOSED;
        }
    }

    /**
     * @return ConnectionState
     */
    public function getState(): ConnectionState
    {
        return $this->state;
    }

    /**
     * @return void
     */
    private function assertConnected(): void
    {
        if ($this->state !== ConnectionState::CONNECTED) {
            throw new LogicException(
                sprintf(
                    'Invalid state: expected CONNECTED, received %s',
                    $this->state->value
                )
            );
        }

        if (!is_resource($this->fp)) {
            throw new LogicException(
                'Socket resource is not available'
            );
        }
    }

    /**
     * @param Throwable $e
     * @return never
     * @throws Throwable
     */
    private function fail(Throwable $e): never
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }

        $this->fp = null;
        $this->state = ConnectionState::ERROR;

        throw $e;
    }
}