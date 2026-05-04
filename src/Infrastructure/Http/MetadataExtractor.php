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

final class MetadataExtractor
{
    public function extract(string $metadataBlock, int $offset): string
    {
        if (!isset($metadataBlock[$offset])) {
            throw new RuntimeException(
                'Metadata offset is out of bounds'
            );
        }
        // ICY metadata block structure:
        // [offset]      = length byte (metadata length / 16)
        // [offset + 1]  = start of actual metadata (e.g., StreamTitle='...';)
        $metaStart = $offset + 1;
        // Find out the length of metadata.
        $metaLength = ord($metadataBlock[$offset]) * 16;

        if ($metaLength === 0) {
            return '';
        }
        // Get metadata in the following format "StreamTitle='artist name and song name';".
        return substr(
            $metadataBlock,
            $metaStart,
            $metaLength
        );
    }
}