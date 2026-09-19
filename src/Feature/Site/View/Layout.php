<?php

declare(strict_types=1);

namespace Nqphp\Feature\Site\View;

class Layout
{
    public static function render(string $title, \Nqphp\Core\Tag\AbstractTag|string $content): string
    {
        $v = time();
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>' . htmlspecialchars($title) . ' - nqphp</title>
    <link rel="stylesheet" href="/css/style.css?v=' . $v . '">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
</head>
<body>
    <div id="drawer-backdrop" class="drawer-backdrop" onclick="closeMenu()"></div>

    <header class="site-header">
        <div class="header-inner">
            <button type="button" class="hamburger-btn" onclick="toggleMenu()" aria-label="Toggle Navigation">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>

            <a href="/" class="logo">
                <span class="logo-icon">⚡</span> nqphp
            </a>

            <nav class="nav-links">
                <a href="/docs" class="nav-link">Docs</a>
                <a href="https://github.com/NQAI-Dev/nqphp" target="_blank" rel="noopener" class="nav-link">GitHub</a>
            </nav>
        </div>
    </header>

    <div class="main-wrapper">
        ' . (is_string($content) ? $content : (method_exists($content, "toHtml") ? $content->toHtml() : (string)$content)) . '
    </div>

    <footer class="site-footer">
        <p>&copy; ' . date('Y') . ' nqphp. Micro-framework with zero magic.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script>
        function toggleMenu() {
            var sidebar = document.querySelector(".docs-sidebar");
            var backdrop = document.getElementById("drawer-backdrop");
            var btn = document.querySelector(".hamburger-btn");
            if (!sidebar || !backdrop) return;
            var isOpen = sidebar.classList.contains("open");
            if (isOpen) {
                closeMenu();
            } else {
                sidebar.classList.add("open");
                backdrop.classList.add("open");
                if (btn) btn.classList.add("is-active");
                document.body.classList.add("menu-open");
            }
        }

        function closeMenu() {
            var sidebar = document.querySelector(".docs-sidebar");
            var backdrop = document.getElementById("drawer-backdrop");
            var btn = document.querySelector(".hamburger-btn");
            if (sidebar) sidebar.classList.remove("open");
            if (backdrop) backdrop.classList.remove("open");
            if (btn) btn.classList.remove("is-active");
            document.body.classList.remove("menu-open");
        }
    </script>
</body>
</html>';
    }
}
