<?php

declare(strict_types=1);

namespace Nqphp\Feature\Docs\Controller;

use Nqphp\Core\Attribute\Controller;
use Nqphp\Core\Attribute\Route;
use Nqphp\Core\Tag\AbstractTag;
use Nqphp\Core\Tag\Tag;
use Nqphp\Feature\Site\View\Layout;
use Parsedown;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Controller]
class DocsController
{
    private function getSidebar(string $currentSlug = ''): AbstractTag
    {
        $links = [
            'Getting Started' => [
                'installation' => 'Installation & Setup',
                'routing' => 'Routing & Attributes',
                'controllers' => 'Controllers & Actions',
            ],
            'Architecture' => [
                'container' => 'DI Container & Services',
                'middleware' => 'Middleware Pipeline',
                'validation' => 'Validation & DTOs',
                'session' => 'Session Management',
            ],
            'Core Components' => [
                'nq-js' => 'Declarative UI & nq.js',
                'html-tags' => 'Typed HTML Tags & UI',
                'cache' => 'Cache & State Stores',
                'events' => 'Event Dispatcher',
                'entity' => 'Entity & SQLite ORM',
            ],
            'CLI & Ops' => [
                'console' => 'CLI & Console Commands',
                'config' => 'Feature Configuration',
            ]
        ];

        $sections = [];
        foreach ($links as $heading => $items) {
            $listItems = [];
            foreach ($items as $slug => $label) {
                $classes = $currentSlug === $slug ? 'active' : '';
                $listItems[] = Tag::li([], Tag::a([
                    'href' => '/docs/' . $slug,
                    'class' => $classes,
                ], $label));
            }
            $sections[] = Tag::h4([], $heading);
            $sections[] = Tag::ul([], $listItems);
        }

        return Tag::div(['class' => 'docs-sidebar'], $sections);
    }

    #[Route('/docs', name: 'docs_index', methods: ['GET'])]
    public function index(): Response
    {
        $intro = '
# nqphp Documentation

**nqphp** is a modern, lightweight PHP 8.4+ micro-framework designed for building fast, predictable, and strictly typed services without magic, bulky abstractions, or heavy dependencies.

---

## Architectural Principles

- **Explicit over Implicit**: No hidden container transformations or unmapped magic wiring.
- **Vertical Slice Architecture**: Code is organized into independent domain slices (`src/Feature/*`) rather than horizontal file layers.
- **Native Attributes**: First-class support for PHP 8 attributes (`#[Route]`, `#[Controller]`, `#[Service]`, `#[EventListener]`, `#[Entity]`, `#[AsCommand]`).
- **Strict Typing**: `declare(strict_types=1)` enforced across all framework components.
- **Zero Config Bloat**: No complex XML or nested YAML configurations — lightweight, typed configuration classes.

---

## Documentation Sections

### 🚀 Getting Started
- [Installation & Setup](/docs/installation) — System requirements, project setup, and directory structure.
- [Routing & Attributes](/docs/routing) — Declarative routing, route parameters, HTTP methods, and `#[BeforeRoute]` hooks.
- [Controllers & Actions](/docs/controllers) — Controller classes, dependency injection, and `AbstractController` helpers.

### 🏛 Architecture
- [DI Container & Services](/docs/container) — Autowiring and service registration via `#[Service]`.
- [Middleware Pipeline](/docs/middleware) — Onion pipeline architecture and error boundary handling.
- [Validation & DTOs](/docs/validation) — Strongly typed input validation and RFC 7807 problem details.
- [Session Management](/docs/session) — Isolated, secure sessions via `SessionInterface`.

### 🧱 Core Components
- [Declarative UI & nq.js](/docs/nq-js) — Lightweight HTMX-like client runtime with declarative attributes and automated CSRF.
- [Typed HTML Tags & UI](/docs/html-tags) — Type-safe HTML DSL without external template engines.
- [Cache & State Stores](/docs/cache) — In-process and PSR-compatible caching with TTL and key namespaces.
- [Event Dispatcher](/docs/events) — Event publishing and subscriptions via `#[EventListener]`.
- [Entity & SQLite ORM](/docs/entity) — Database schemas, typed entities, and `EntityManager`.

### ⚙️ CLI & Ops
- [CLI & Console Commands](/docs/console) — Building terminal commands using `#[AsCommand]`.
- [Feature Configuration](/docs/config) — Typed per-feature configurations via `FeatureConfig`.
';
        $parsedown = new Parsedown();
        $html = $parsedown->text($intro);

        $main = Tag::div(['class' => 'docs-content markdown-body'], [Tag::raw($html)]);
        $layout = Tag::div(['class' => 'grid'], [
            $this->getSidebar(''),
            $main
        ]);

        return new Response(Layout::render('Overview - Documentation', $layout));
    }

    #[Route('/docs/{slug}', name: 'docs_page', methods: ['GET'])]
    public function page(string $slug): Response
    {
        $path = __DIR__ . '/../../../../docs/' . $slug . '.md';
        
        if (!file_exists($path)) {
            throw new NotFoundHttpException('Documentation page not found');
        }

        $markdown = file_get_contents($path);
        
        $parsedown = new Parsedown();
        $html = $parsedown->text($markdown);

        $main = Tag::div(['class' => 'docs-content markdown-body'], [Tag::raw($html)]);

        $layout = Tag::div(['class' => 'grid'], [
            $this->getSidebar($slug),
            $main
        ]);

        $title = ucwords(str_replace(['-', '_'], ' ', $slug));

        return new Response(Layout::render($title . ' - Documentation', $layout));
    }
}
