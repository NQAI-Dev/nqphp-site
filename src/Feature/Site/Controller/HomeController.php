<?php

declare(strict_types=1);

namespace Nqphp\Feature\Site\Controller;

use Nqphp\Core\Attribute\Controller;
use Nqphp\Core\Attribute\Route;
use Nqphp\Core\Tag\Tag;
use Nqphp\Core\Tag\Div;
use Nqphp\Core\Tag\A;
use Nqphp\Core\Tag\Span;
use Nqphp\Feature\Site\View\Layout;
use Symfony\Component\HttpFoundation\Response;

#[Controller]
class HomeController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        $hero = Tag::div(['class' => 'hero'], [
            Tag::h1([], 'nqphp'),
            Tag::p([], 'A fast, modern, attribute-driven PHP framework.'),
            Tag::div(['class' => 'badges'], [
                Tag::span(['class' => 'badge'], 'PHP 8.2+'),
                Tag::span(['class' => 'badge'], 'Zero Magic'),
                Tag::span(['class' => 'badge'], 'Fast Router')
            ]),
            Tag::a(['href' => '/docs', 'role' => 'button'], 'Get Started'),
            Tag::a(['href' => 'https://github.com/nqai/nqphp', 'role' => 'button', 'class' => 'secondary'], 'View on GitHub'),
        ]);

        $features = Tag::div(['class' => 'feature-grid'], [
            Tag::div(['class' => 'card'], [
                Tag::h3([], 'Attribute-Driven'),
                Tag::p([], 'Define routes, commands, and events right where they belong using native PHP 8 attributes.')
            ]),
            Tag::div(['class' => 'card'], [
                Tag::h3([], 'Type-Safe'),
                Tag::p([], 'Leverage strict typing and static analysis tools. Built for PHP 8.2 and beyond.')
            ]),
            Tag::div(['class' => 'card'], [
                Tag::h3([], 'No Magic'),
                Tag::p([], 'Explicit wiring and straightforward execution paths. No hidden global state.')
            ])
        ]);

        $content = Tag::div([], [$hero, $features]);

        return new Response(Layout::render('Fast, Modern PHP Framework', $content));
    }
}
