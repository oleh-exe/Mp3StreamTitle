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

namespace Mp3StreamTitle\Domain\ValueObject;

enum Scheme: string
{
    case HTTP = 'http';

    case HTTPS = 'https';

    /**
     * Determines if the protocol is secure.
     *
     * @return bool True if the protocol is secure (e.g., HTTPS), false otherwise.
     */
    public function isSecure(): bool
    {
        return $this === self::HTTPS;
    }

    /**
     * Converts the protocol to its corresponding transport layer protocol.
     *
     * @return Transport The transport layer protocol associated with the protocol (e.g., TCP for HTTP, TLS for HTTPS).
     */
    public function toTransport(): Transport
    {
        return match ($this) {
            self::HTTP => Transport::TCP,
            self::HTTPS => Transport::TLS,
        };
    }

    /**
     * Returns the default port associated with the protocol.
     *
     * @return int The default port number (e.g., 80 for HTTP, 443 for HTTPS).
     */
    public function defaultPort(): int
    {
        return match ($this) {
            self::HTTP => 80,
            self::HTTPS => 443,
        };
    }
}