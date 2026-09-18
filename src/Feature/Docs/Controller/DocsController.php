<?php

declare(strict_types=1);

namespace Nqphp\Feature\Docs\Controller;

use Nqphp\Core\Attribute\Controller;
use Nqphp\Core\Attribute\Route;
use Nqphp\Core\Tag\Tag;
use Nqphp\Feature\Site\View\Layout;
use Symfony\Component\HttpFoundation\Response;

#[Controller]
class DocsController
{
    #[Route('/docs', name: 'docs_index', methods: ['GET'])]
    public function index(): Response
    {
        $sidebar = Tag::div(['class' => 'docs-sidebar'], [
            Tag::h4([], 'Getting Started'),
            Tag::ul([], [
                Tag::li([], Tag::a(['href' => '/docs/installation'], 'Installation')),
                Tag::li([], Tag::a(['href' => '/docs/routing'], 'Routing')),
                Tag::li([], Tag::a(['href' => '/docs/controllers'], 'Controllers')),
            ])
        ]);

        $main = Tag::div(['class' => 'docs-content'], [
            Tag::h2([], 'Documentation'),
            Tag::p([], 'Welcome to the nqphp documentation. Choose a topic from the sidebar to get started.'),
            Tag::p([], 'nqphp is designed to be simple and transparent, avoiding magic where possible.')
        ]);

        $layout = Tag::div(['class' => 'grid'], [
            $sidebar,
            $main
        ]);

        return new Response(Layout::render('Documentation', $layout));
    }

    #[Route('/docs/{slug}', name: 'docs_page', methods: ['GET'])]
    public function page(string $slug): Response
    {
        $sidebar = Tag::div(['class' => 'docs-sidebar'], [
            Tag::h4([], 'Getting Started'),
            Tag::ul([], [
                Tag::li([], Tag::a(['href' => '/docs/installation'], 'Installation')),
                Tag::li([], Tag::a(['href' => '/docs/routing'], 'Routing')),
                Tag::li([], Tag::a(['href' => '/docs/controllers'], 'Controllers')),
            ])
        ]);

        $title = ucfirst($slug);
        
        $main = Tag::div(['class' => 'docs-content'], [
            Tag::h2([], $title),
            Tag::p([], "Content for $title goes here. In a real setup, this would parse Markdown from a docs directory."),
            Tag::pre([], Tag::code([], "composer require nqai/nqphp\nbin/console serve"))
        ]);

        $layout = Tag::div(['class' => 'grid'], [
            $sidebar,
            $main
        ]);

        return new Response(Layout::render($title . ' - Documentation', $layout));
    }
}
