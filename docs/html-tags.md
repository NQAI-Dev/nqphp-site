# Typed HTML Tags & UI

`nqphp` provides a built-in, object-oriented DSL for generating safe, valid HTML without external template engines such as Twig or Blade.

## Why Typed Tags?

1. **Security**: All text node values are automatically escaped via `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5)`. XSS vulnerabilities are mitigated at the architecture level.
2. **Type Safety & Autocompletion**: Modern IDEs offer full code completion and signature validation for standard element methods (`Tag::div()`, `Tag::h1()`, `Tag::a()`).
3. **Zero Overhead**: No template compilation step, no AST parsing, and no disk caching for generated PHP templates.

## Basic Usage

Create HTML elements through the static `Tag` builder:

```php
use Nqphp\Core\Tag\Tag;

// Basic tag with string contents
$header = Tag::h1([], 'Page Title');

// Tag with HTML attributes and nested child elements
$card = Tag::div(['class' => 'card shadow-sm'], [
    Tag::h2(['class' => 'card-title'], 'Product #1'),
    Tag::p(['class' => 'card-text'], 'Product description text.'),
    Tag::a(['href' => '/buy/1', 'class' => 'btn btn-primary'], 'Purchase')
]);

echo $card->toHtml();
```

## Rendering Raw HTML (`Tag::raw`)

When outputting trusted markup (for example, output from a parsed Markdown document):

```php
$parsedHtml = $parsedown->text($markdownContent);

// Tag::raw wraps HTML markup without double-escaping
$content = Tag::div(['class' => 'prose'], [
    Tag::raw($parsedHtml)
]);
```
