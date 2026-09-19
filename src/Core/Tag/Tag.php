<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * Static factory for typed HTML tag builders.
 */
final class Tag
{
    public static function raw(string $html): Raw
    {
        return new Raw($html);
    }

    public static function div(array $attributes = [], string|AbstractTag|array $children = []): Div
    {
        return new Div($attributes, $children);
    }

    public static function h1(array $attributes = [], string|AbstractTag|array $children = []): H1
    {
        return new H1($attributes, $children);
    }

    public static function h2(array $attributes = [], string|AbstractTag|array $children = []): H2
    {
        return new H2($attributes, $children);
    }

    public static function h4(array $attributes = [], string|AbstractTag|array $children = []): H4
    {
        return new H4($attributes, $children);
    }

    public static function h3(array $attributes = [], string|AbstractTag|array $children = []): H3
    {
        return new H3($attributes, $children);
    }

    public static function p(array $attributes = [], string|AbstractTag|array $children = []): P
    {
        return new P($attributes, $children);
    }

    public static function span(array $attributes = [], string|AbstractTag|array $children = []): Span
    {
        return new Span($attributes, $children);
    }

    public static function pre(array $attributes = [], string|AbstractTag|array $children = []): Pre
    {
        return new Pre($attributes, $children);
    }

    public static function code(array $attributes = [], string|AbstractTag|array $children = []): Code
    {
        return new Code($attributes, $children);
    }

    public static function ul(array $attributes = [], string|AbstractTag|array $children = []): Ul
    {
        return new Ul($attributes, $children);
    }

    public static function li(array $attributes = [], string|AbstractTag|array $children = []): Li
    {
        return new Li($attributes, $children);
    }

    public static function a(array $attributes = [], string|AbstractTag|array $children = []): A
    {
        return new A($attributes, $children);
    }

    public static function img(array $attributes = []): Img
    {
        return new Img($attributes, []);
    }

    public static function br(array $attributes = []): Br
    {
        return new Br($attributes, []);
    }

    public static function hr(array $attributes = []): Hr
    {
        return new Hr($attributes, []);
    }

    public static function input(array $attributes = []): Input
    {
        return new Input($attributes, []);
    }

    public static function textarea(array $attributes = [], string|AbstractTag|array $children = []): Textarea
    {
        return new Textarea($attributes, $children);
    }

    public static function label(array $attributes = [], string|AbstractTag|array $children = []): Label
    {
        return new Label($attributes, $children);
    }

    public static function select(array $attributes = [], string|AbstractTag|array $children = []): Select
    {
        return new Select($attributes, $children);
    }

    public static function option(array $attributes = [], string|AbstractTag|array $children = []): Option
    {
        return new Option($attributes, $children);
    }
}
