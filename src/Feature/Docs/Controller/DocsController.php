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
    private function getSidebar(): AbstractTag
    {
        return Tag::div(['class' => 'docs-sidebar'], [
            Tag::h4([], 'Getting Started'),
            Tag::ul([], [
                Tag::li([], Tag::a(['href' => '/docs/installation'], 'Installation')),
                Tag::li([], Tag::a(['href' => '/docs/routing'], 'Routing')),
                Tag::li([], Tag::a(['href' => '/docs/controllers'], 'Controllers')),
            ])
        ]);
    }

    #[Route('/docs', name: 'docs_index', methods: ['GET'])]
    public function index(): Response
    {
        $main = Tag::div(['class' => 'docs-content'], [
            Tag::h2([], 'Documentation'),
            Tag::p([], 'Welcome to the nqphp documentation. Choose a topic from the sidebar to get started.'),
            Tag::p([], 'nqphp is designed to be simple and transparent, avoiding magic where possible.')
        ]);

        $layout = Tag::div(['class' => 'grid'], [
            $this->getSidebar(),
            $main
        ]);

        return new Response(Layout::render('Documentation', $layout));
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
        // Parsedown is safe by default, but let's parse it and wrap it as a raw string
        $html = $parsedown->text($markdown);

        // We wrap raw HTML in a div
        $main = Tag::div(['class' => 'docs-content markdown-body'], Tag::raw($html));

        $layout = Tag::div(['class' => 'grid'], [
            $this->getSidebar(),
            $main
        ]);

        // Basic title generation from slug
        $title = ucwords(str_replace('-', ' ', $slug));

        return new Response(Layout::render($title . ' - Documentation', $layout));
    }
}
