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

**nqphp** — современный микрофреймворк на PHP 8.4+, спроектированный для построения производительных, предсказуемых и строго типизированных сервисов без магии, тяжелых абстракций и громоздких зависимостей.

---

## Архитектурные принципы

- **Explicit over Implicit**: Никаких скрытых контейнерных трансформаций или автогенерации неявных связей.
- **Feature-Sliced Design**: Код организован по независимым доменным слайсам (`src/Feature/*`), а не по типам файлов.
- **Native Attributes**: Полное использование PHP 8.4 attributes (`#[Route]`, `#[Controller]`, `#[Service]`, `#[EventListener]`, `#[Entity]`, `#[AsCommand]`).
- **Строгая типизация**: `declare(strict_types=1)` по умолчанию во всех компонентах.
- **Zero Config Bloat**: Без YAML, XML или многоуровневых конфигураций — только строгие типизированные классы настроек.

---

## Разделы документации

### 🚀 Getting Started
- [Installation & Setup](/docs/installation) — системные требования, развертывание проекта, структура каталогов.
- [Routing & Attributes](/docs/routing) — декларативный роутинг, параметры маршрутов, HTTP-методы и хуки `#[BeforeRoute]`.
- [Controllers & Actions](/docs/controllers) — создание контроллеров, инъекция зависимостей, методы `AbstractController`.

### 🏛 Architecture
- [DI Container & Services](/docs/container) — автовайринг, регистрация сервисов через `#[Service]`.
- [Middleware Pipeline](/docs/middleware) — onion-пайплайн, изоляция ошибок через error boundary.
- [Validation & DTOs](/docs/validation) — типизированная валидация входных данных, 422 Problem Details.
- [Session Management](/docs/session) — работа с изолированными сессиями через `SessionInterface`.

### 🧱 Core Components
- [Typed HTML Tags & UI](/docs/html-tags) — типобезопасный HTML DSL без шаблонизаторов и защита от XSS.
- [Cache & State Stores](/docs/cache) — встроенное in-process и PSR кеширование с TTL и namespace.
- [Event Dispatcher](/docs/events) — подписка и диспетчеризация событий через `#[EventListener]`.
- [Entity & SQLite ORM](/docs/entity) — работа с базой данных, схемы сущностей, `EntityManager`.

### ⚙️ CLI & Ops
- [CLI & Console Commands](/docs/console) — создание терминальных команд через `#[AsCommand]`.
- [Feature Configuration](/docs/config) — типизированные конфигурации через `FeatureConfig`.
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
