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
    public static function div(): Div
    {
        return new Div();
    }

    /** @return Span a fresh, empty <span> builder. */
    public static function h1(array $attributes = [], string|AbstractTag|array $children = []): H1
    {
        return new H1($attributes, $children);
    }

    public static function h3(array $attributes = [], string|AbstractTag|array $children = []): H3
    {
        return new H3($attributes, $children);
    }

    public static function p(array $attributes = [], string|AbstractTag|array $children = []): P
    {
        return new P($attributes, $children);
    }

    public static function span(): Span
    {
        return new Span();
    }

    /** @return A a fresh, empty <a> builder. */
    public static function a(): A
    {
        return new A();
    }

    /** @return Img a fresh, empty <img> builder (self-closing). */
    public static function img(): Img
    {
        return new Img();
    }

    /** @return Br a fresh, empty <br> builder (self-closing). */
    public static function br(): Br
    {
        return new Br();
    }

    /** @return Hr a fresh, empty <hr> builder (self-closing). */
    public static function hr(): Hr
    {
        return new Hr();
    }

    /** @return Input a fresh, empty <input> builder (self-closing). */
    public static function input(): Input
    {
        return new Input();
    }

    /** @return Textarea a fresh, empty <textarea> builder. */
    public static function textarea(): Textarea
    {
        return new Textarea();
    }

    /** @return Label a fresh, empty <label> builder. */
    public static function label(): Label
    {
        return new Label();
    }

    /** @return Select a fresh, empty <select> builder. */
    public static function select(): Select
    {
        return new Select();
    }

    /** @return Option a fresh, empty <option> builder. */
    public static function option(): Option
    {
        return new Option();
    }
}
