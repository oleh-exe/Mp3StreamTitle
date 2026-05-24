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

use RuntimeException;
use Throwable;

final class StreamReader
{
    /**
     * Reads data from a socket connection until the desired length is achieved.
     *
     * @param SocketConnection $socket The socket connection to read data from.
     * @param string $initialBuffer The initial buffer containing previously read data.
     * @param int $targetLength The target length of data to be read.
     * @param int $maxAllowed The maximum allowed length for the buffer.
     *
     * @return string The buffer containing the required data.
     *
     * @throws Throwable
     */
    public function read(
        SocketConnection $socket,
        string $initialBuffer,
        int $targetLength,
        int $maxAllowed
    ): string {
        if (strlen($initialBuffer) < $targetLength) {
            $this->readUntilLength($socket, $initialBuffer, $targetLength, $maxAllowed);
        }

        return $initialBuffer;
    }

    /**
     * Continues reading data from a socket connection until the target length is reached.
     *
     * @param SocketConnection $socket The socket connection to read data from.
     * @param string &$initialBuffer A reference to the buffer containing previously read data, which will be updated with additional data.
     * @param int $targetLength The target length of data to be reached within the buffer.
     * @param int $maxAllowed The maximum allowed length for the buffer, beyond which an exception will be thrown.
     *
     * @return void
     *
     * @throws RuntimeException|Throwable If the buffer exceeds the maximum allowed length.
     */
    private function readUntilLength(
        SocketConnection $socket,
        string &$initialBuffer,
        int $targetLength,
        int $maxAllowed
    ): void {
        while (strlen($initialBuffer) < $targetLength) {
            $initialBuffer .= $socket->read();

            if (strlen($initialBuffer) > $maxAllowed) {
                throw new RuntimeException(
                    sprintf('Stream body exceeded maximum allowed length (%d bytes)', $maxAllowed)
                );
            }
        }
    }
}