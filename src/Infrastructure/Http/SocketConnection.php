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
     *
     * @var ConnectionState $state
     */
    private ConnectionState $state = ConnectionState::INITIAL;

    /**
     * @param string $host The hostname to connect to.
     * @param int $port The port number to connect on.
     * @param Transport $transport The transport mechanism to be used.
     * @param int $timeout The connection timeout in seconds; must be greater than 0.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the timeout is less than or equal to 0.
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
     * Opens a connection to the specified remote address using the defined
     * transport, host, and port. Handles connection errors and sets the
     * connection state appropriately.
     *
     * @return void
     *
     * @throws LogicException If the connection cannot be opened from the current state
     *                        or if it has previously failed and cannot be reused.
     * @throws SocketConnectionException|Throwable If the connection fails, or if stream settings
     *                                    (blocking mode or timeout) cannot be configured.
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
     * Writes the given data to the socket connection.
     *
     * @param string $data The data to be written to the connection.
     *
     * @return void
     *
     * @throws SocketConnectionException If writing to the socket fails.
     * @throws Throwable If an unexpected error occurs during the write operation.
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
     * Reads data from the socket in chunks of a fixed length.
     *
     * @return string The data read from the socket.
     *
     * @throws SocketConnectionException If the read operation fails due to errors, timeout, EOF, or unexpected conditions.
     * @throws Throwable If any other unexpected exception occurs during the operation.
     */
    public function read(): string
    {
        $this->assertConnected();

        $this->state = ConnectionState::READING;

        try {
            $length = 8192;
            $chunk = fread($this->fp, $length);

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

            $this->state = ConnectionState::CONNECTED;

            return $chunk;
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Closes the current resource and updates the connection state.
     *
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
     * Ensures the current state is valid and the socket resource is available.
     *
     * @return void
     *
     * @throws LogicException If the current state is not CONNECTED or the socket resource is unavailable.
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
     * Handles a critical failure in the connection by closing the resource, clearing
     * the internal state, and throwing the provided exception.
     *
     * @param Throwable $e The exception to be thrown indicating the failure.
     *
     * @return never This method does not return a value as it always throws an exception.
     *
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