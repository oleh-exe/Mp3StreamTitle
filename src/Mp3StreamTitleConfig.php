<?php

namespace Mp3StreamTitle;

final class Mp3StreamTitleConfig
{
    /**
     * Indicate which function to use to send requests to the stream-server.
     * 1 — cURL-function.
     * 2 — Socket-function.
     * 3 — FGC-function.
     *
     * @var int
     */
    public readonly int $sendType;

    /**
     * The contents of our "User-Agent" HTTP-header.
     *
     * @var string
     */
    public readonly string $userAgent;

    /**
     * Enable or disable the display of error messages.
     * false — Error messages display disabled.
     * true — Error messages display enabled.
     *
     * @var bool
     */
    public readonly bool $showErrors;

    /**
     * Maximum metadata length in bytes.
     *
     * @var int
     */
    public readonly int $metaMaxLength;

    public function __construct(
        int $sendType = Mp3StreamTitle::SEND_CURL,
        string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        bool $showErrors = false,
        int $metaMaxLength = 4080
    ) {
        if ($userAgent === '') {
            throw new InvalidArgumentException('User-Agent cannot be empty');
        }

        if ($metaMaxLength > 4080) {
            throw new InvalidArgumentException('metaMaxLength must be no more than 4080 bytes');
        }

        $this->sendType = $sendType;
        $this->userAgent = $userAgent;
        $this->showErrors = $showErrors;
        $this->metaMaxLength = $metaMaxLength;
    }
}