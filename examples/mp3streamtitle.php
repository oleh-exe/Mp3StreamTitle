<?php

/**
 * Example code from the "Mp3StreamTitle" project
 * Copyright 2020-2025 Oleh Kovalenko
 *
 * Licensed under the Apache License, Version 2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Note: This is example/demo code. Use at your own risk ("AS IS").
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Mp3StreamTitle.php';

use Mp3StreamTitle\Mp3StreamTitle;

$mp3 = new Mp3StreamTitle();

var_dump($mp3->sendRequest('https://cast1.torontocast.com:4450/stream/1/'));
