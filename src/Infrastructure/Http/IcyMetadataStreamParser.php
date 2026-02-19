<?php

declare(strict_types=1);

namespace Mp3StreamTitle\Infrastructure\Http;

final class IcyMetadataStreamParser
{
    /**
     * @var string
     */
    private string $buffer = '';

    /**
     * @var string|null
     */
    private ?string $metadata = null;

    /**
     * Constructs a new instance of the class with the given offset and meta-max length.
     *
     * @param int $offset The offset value, which must be greater than 0.
     * @param int $metaMaxLength The maximum length for meta-information, which must be greater than 0.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If $offset or $metaMaxLength are less than or equal to 0.
     */
    public function __construct(
        private readonly int $offset,
        private readonly int $metaMaxLength
    ) {
        if ($offset <= 0) {
            throw new \InvalidArgumentException('Offset must be greater than 0');
        }

        if ($metaMaxLength <= 0) {
            throw new \InvalidArgumentException('Meta-max length must be greater than 0');
        }
    }

    /**
     * Appends a chunk of data to the internal buffer and processes metadata if certain conditions are met.
     *
     * @param string $chunk The chunk of data to append to the buffer.
     *
     * @return bool Returns true if the metadata is successfully processed,
     * or false if the buffer does not yet contain enough data.
     */
    public function append(string $chunk): bool
    {
        $this->buffer .= $chunk;

        $requiredLength = $this->offset + $this->metaMaxLength;

        if (strlen($this->buffer) < $requiredLength) {
            return false;
        }

        $metaLength = ord($this->buffer[$this->offset]) * 16;

        if ($metaLength === 0) {
            $this->metadata = '';
            return true;
        }

        $this->metadata = substr(
            $this->buffer,
            $this->offset + 1,
            $metaLength
        );

        return true;
    }

    /**
     * Retrieves the metadata associated with the object.
     *
     * @return string|null The metadata string if available, or null if no metadata is set.
     */
    public function getMetadata(): ?string
    {
        return $this->metadata;
    }
}