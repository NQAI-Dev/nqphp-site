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
                'installation' => 'Installation',
                'routing' => 'Routing & Attributes',
                'controllers' => 'Controllers & Actions',
            ],
            'Architecture' => [
                'container' => 'DI Container & Services',
                'middleware' => 'Middleware & Pipeline',
                'validation' => 'Validation & DTOs',
                'session' => 'Session & State',
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

**nqphp** — легковесный, высокопроизводительный PHP-фреймворк без скрытой магии и оверхеда. Построен вокруг современных возможностей PHP 8.4+, явных интерфейсов и компонентной архитектуры.

---

### Основные концепции
* **Явный Dependency Injection**: автовайринг через атрибуты и интерфейсы без громоздких XML/YAML-конфигов.
* **Атрибутная маршрутизация**: контроллеры и роуты декларируются декларативно прямо в коде (`#[Route]`, `#[Controller]`).
* **Typed HTML Tags**: безопасный рендеринг представлений без компиляции тяжелых шаблонизаторов (`Tag::div`, `Tag::h1`, `Tag::raw`).
* **Strict PSR / Modern Standards**: строгая типизация `declare(strict_types=1)`, поддержка Middleware-луковицы и типизированных DTO с автоматической валидацией.

Выберите раздел в боковом меню для перехода к деталям реализации.
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
