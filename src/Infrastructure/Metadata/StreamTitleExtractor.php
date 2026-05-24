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

namespace Mp3StreamTitle\Infrastructure\Metadata;

use RuntimeException;

final readonly class StreamTitleExtractor
{
    /**
     * Extracts StreamTitle value from ICY metadata.
     *
     * Example:
     * StreamTitle='artist name and song name';
     *
     * Returns:
     * Artist name and song name
     *
     * @param string $metadata
     *
     * @return string
     */
    public function extract(string $metadata): string
    {
        $prefix = "StreamTitle='";
        $suffix = "';";

        $start = strpos($metadata, $prefix);

        if ($start === false) {
            throw new RuntimeException(
                'StreamTitle field not found in metadata'
            );
        }

        $valueStart = $start + strlen($prefix);

        $end = strpos($metadata, $suffix, $valueStart);

        if ($end === false) {
            throw new RuntimeException(
                'StreamTitle closing delimiter not found'
            );
        }

        return substr(
            $metadata,
            $valueStart,
            $end - $valueStart
        );
    }
}