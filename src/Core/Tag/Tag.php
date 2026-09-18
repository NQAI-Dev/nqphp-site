<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * Static factory for typed HTML tag builders.
 *
 * Usage:
 *   echo Tag::div()->setClass('container')->setContent('hi');
 *   echo Tag::span()->setContent('inline');
 *   echo Tag::a('/about', 'About');
 *
 * New HTML elements can be added by adding a static method here
 * once the concrete subclass ships. No magic __call — explicit
 * factory methods give IDE autocomplete and static analysis.
 */
final class Tag
{
    /** @return Div a fresh, empty <div> builder. */
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

    public static function ul(array $attributes = [], string|AbstractTag|array $children = []): Ul
    {
        return new Ul($attributes, $children);
    }

    public static function li(array $attributes = [], string|AbstractTag|array $children = []): Li
    {
        return new Li($attributes, $children);
    }

    /** @return A a fresh, empty <a> builder. */
    public static function a(array $attributes = [], string|AbstractTag|array $children = []): A
    {
        return new A($attributes, $children);
    }

    /** @return Img a fresh, empty <img> builder (self-closing). */
    public static function img(array $attributes = []): Img
    {
        return new Img($attributes, []);
    }

    /** @return Br a fresh, empty <br> builder (self-closing). */
    public static function br(array $attributes = []): Br
    {
        return new Br($attributes, []);
    }

    /** @return Hr a fresh, empty <hr> builder (self-closing). */
    public static function hr(array $attributes = []): Hr
    {
        return new Hr($attributes, []);
    }

    /** @return Input a fresh, empty <input> builder (self-closing). */
    public static function input(array $attributes = []): Input
    {
        return new Input($attributes, []);
    }

    /** @return Textarea a fresh, empty <textarea> builder. */
    public static function textarea(array $attributes = [], string|AbstractTag|array $children = []): Textarea
    {
        return new Textarea($attributes, $children);
    }

    /** @return Label a fresh, empty <label> builder. */
    public static function label(array $attributes = [], string|AbstractTag|array $children = []): Label
    {
        return new Label($attributes, $children);
    }

    /** @return Select a fresh, empty <select> builder. */
    public static function select(array $attributes = [], string|AbstractTag|array $children = []): Select
    {
        return new Select($attributes, $children);
    }

    /** @return Option a fresh, empty <option> builder. */
    public static function option(array $attributes = [], string|AbstractTag|array $children = []): Option
    {
        return new Option($attributes, $children);
    }
}
