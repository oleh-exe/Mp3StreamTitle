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

final class IcyMetaIntExtractor
{
    /**
     * Retrieves and validates the "icy-metaint" header value from the given HTTP response.
     *
     * @param HttpResponse $httpResponse The HTTP response object containing headers.
     *
     * @return int The validated "icy-metaint" header value.
     *
     * @throws RuntimeException If the "icy-metaint" header is not found, contains an invalid value, or is not a positive integer.
     */
    public function getMetaInt(HttpResponse $httpResponse): int
    {
        if (!$httpResponse->headers->has('icy-metaint')) {
            throw new RuntimeException('Header "icy-metaint" not found');
        }
        // Looking for the header "icy-metaint".
        $value = $httpResponse->headers->get('icy-metaint');
        $result = filter_var($value, FILTER_VALIDATE_INT);

        if ($result === false) {
            throw new RuntimeException('Invalid "icy-metaint" header value');
        }

        if ($result <= 0) {
            throw new RuntimeException('Invalid "icy-metaint" header value');
        }

        return $result;
    }
}