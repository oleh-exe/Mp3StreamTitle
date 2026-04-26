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

namespace Mp3StreamTitle\Infrastructure\Http\Request;

use Mp3StreamTitle\Application\Config\Mp3StreamTitleConfig;
use Mp3StreamTitle\Domain\ValueObject\StreamEndpoint;
use Mp3StreamTitle\Infrastructure\Http\Enum\HttpMethod;
use Mp3StreamTitle\Infrastructure\Http\Enum\HttpVersion;

final class StreamRequestFactory
{
    /**
     * @param StreamEndpoint $endpoint
     * @param Mp3StreamTitleConfig $config
     * @return HttpRequest
     */
    public function create(StreamEndpoint $endpoint, Mp3StreamTitleConfig $config): HttpRequest
    {
        $headers = new HeaderCollection([
            'User-Agent' => $config->userAgent
        ]);

        $host = $endpoint->getHost();
        $port = $endpoint->getPort();

        if ($port !== 80 && $port !== 443) {
            $host .= ':' . $port;
        }
        // immutable chain
        $headers = $headers
            ->with('Host', $host)
            ->with('Icy-MetaData', '1'); // forcedly

        return new HttpRequest(
            method: HttpMethod::GET,
            target: $endpoint->getRequestTarget(),
            httpVersion: HttpVersion::HTTP_1_0,
            headers: $headers,
        );
    }
}