<?php

namespace Mp3StreamTitle;

final class Mp3StreamTitleConfig
{
    public readonly int $sendType = Mp3StreamTitle::SEND_CURL;
    public readonly string $userAgent;
    public readonly bool $showErrors;
    public readonly int $metaMaxLength;

    public function __construct(
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

        $this->userAgent = $userAgent;
        $this->showErrors = $showErrors;
        $this->metaMaxLength = $metaMaxLength;
    }
}