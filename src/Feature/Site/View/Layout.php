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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>' . htmlspecialchars($title) . ' - nqphp</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
</head>
<body>
    <div id="drawer-backdrop" class="drawer-backdrop" onclick="closeDrawer()"></div>

    <header class="site-header">
        <div class="header-inner">
            <div class="header-left">
                <button type="button" class="sidebar-toggle-btn" onclick="toggleDrawer()" aria-label="Open documentation menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                    <span>Menu</span>
                </button>
                <a href="/" class="logo">
                    <span class="logo-icon">⚡</span> nqphp
                </a>
            </div>

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
        function toggleDrawer() {
            var sidebar = document.querySelector(".docs-sidebar");
            var backdrop = document.getElementById("drawer-backdrop");
            if (sidebar && backdrop) {
                var isOpen = sidebar.classList.contains("open");
                if (isOpen) {
                    closeDrawer();
                } else {
                    sidebar.classList.add("open");
                    backdrop.classList.add("open");
                    document.body.classList.add("menu-open");
                }
            }
        }

        function closeDrawer() {
            var sidebar = document.querySelector(".docs-sidebar");
            var backdrop = document.getElementById("drawer-backdrop");
            if (sidebar) sidebar.classList.remove("open");
            if (backdrop) backdrop.classList.remove("open");
            document.body.classList.remove("menu-open");
        }
    </script>
</body>
</html>';
    }
}
