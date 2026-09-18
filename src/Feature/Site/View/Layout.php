<?php

declare(strict_types=1);

namespace Nqphp\Feature\Site\View;

use Nqphp\Core\Tag\AbstractTag;
use Nqphp\Core\Tag\Tag;

class Layout
{
    public static function render(string $title, string|Tag $content): string
    {
        return '<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' - nqphp</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="container">
        <nav>
            <ul>
                <li><strong><a href="/">nqphp</a></strong></li>
            </ul>
            <ul>
                <li><a href="/docs">Documentation</a></li>
                <li><a href="https://github.com/nqai/nqphp" target="_blank">GitHub</a></li>
            </ul>
        </nav>
    </header>
    <main class="container">
        ' . (is_string($content) ? $content : (method_exists($content, "render") ? $content->render() : (string)$content)) . '
    </main>
    <footer class="container">
        <hr>
        <p><small>&copy; ' . date('Y') . ' nqphp. Built with nqphp.</small></p>
    </footer>
</body>
</html>';
    }
}
