<?php

declare(strict_types=1);

namespace Nqphp\Feature\Site\View;

class Layout
{
    public static function render(string $title, \Nqphp\Core\Tag\AbstractTag|string $content): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' - nqphp</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
</head>
<body>
    <header>
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; font-size: 1.25rem;">
                <a href="/" style="color: #fff; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: var(--primary);">⚡</span> nqphp
                </a>
            </div>
            <nav>
                <ul style="display: flex; gap: 1.5rem; list-style: none; align-items: center; margin: 0; padding: 0;">
                    <li><a href="/docs" style="color: var(--fg); text-decoration: none; font-weight: 500;">Docs</a></li>
                    <li><a href="https://github.com/NQAI-Dev/nqphp" target="_blank" style="color: var(--fg-muted); text-decoration: none;">GitHub</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">
        ' . (is_string($content) ? $content : (method_exists($content, "toHtml") ? $content->toHtml() : (string)$content)) . '
    </main>
    <footer class="container" style="border-top: 1px solid var(--border); margin-top: 4rem; padding: 2rem 0; color: var(--fg-muted); font-size: 0.9rem; text-align: center;">
        <p>&copy; ' . date('Y') . ' nqphp. Micro-framework with zero magic.</p>
    </footer>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
</body>
</html>';
    }
}
