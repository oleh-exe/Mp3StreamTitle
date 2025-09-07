# 🎵 MP3 Stream Title

![PHP Version](https://img.shields.io/badge/php-%3E%3D7.2-777bb3.svg?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-Apache%202.0-green.svg)
![Stand with Ukraine](https://img.shields.io/badge/Stand%20with-Ukraine-blue?style=flat&logo=flag-ukraine)

A lightweight PHP library to fetch the **currently playing track** from any online radio stream.

## ✨ Features
- ⚡ Lightweight
- 📦 No dependencies
- 🧩 Easy to use
- 🌐 Optional: PHP cURL support for better stream handling

## ⚡ Requirements
- PHP >= 7.2
- PHP cURL recommended but not required

## 📖 Usage
```php
<?php

require_once('Mp3StreamTitle/Mp3StreamTitle.php');

use Mp3StreamTitle\Mp3StreamTitle;

$mp3 = new Mp3StreamTitle();

// Replace with a direct radio stream link
echo $mp3->sendRequest('http://example.com');
```

## 👨‍💻 Author
- [Oleh Kovalenko](https://github.com/oleh-exe) — Owner & Maintainer

## 📜 License
[Apache 2.0](LICENSE)  