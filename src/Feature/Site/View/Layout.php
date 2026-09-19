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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>' . htmlspecialchars($title) . ' - nqphp</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">
                <a href="/">
                    <span class="logo-icon">⚡</span> nqphp
                </a>
            </div>
            <div class="header-right">
                <nav class="nav-links">
                    <ul>
                        <li><a href="/docs" class="nav-link-docs">Docs</a></li>
                        <li><a href="https://github.com/NQAI-Dev/nqphp" target="_blank">GitHub</a></li>
                    </ul>
                </nav>
                <button type="button" class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </header>
    <main class="container">
        ' . (is_string($content) ? $content : (method_exists($content, "toHtml") ? $content->toHtml() : (string)$content)) . '
    </main>
    <footer class="container">
        <p>&copy; ' . date('Y') . ' nqphp. Micro-framework with zero magic.</p>
    </footer>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script>
        function toggleSidebar() {
            var sidebar = document.querySelector(".docs-sidebar");
            if (sidebar) {
                sidebar.classList.toggle("open");
            }
        }
    </script>
</body>
</html>';
    }
}
