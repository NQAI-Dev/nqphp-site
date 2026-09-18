<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * Anchor (<a>) tag — used so often it's worth a typed class.
 *
 * Convenience:
 *   echo A::to('https://example.com', 'link text');
 */
/** <a> HTML element builder. Use Tag::a() rather than instantiating directly. */
final class A extends AbstractTag
{
    /** Tag name is hard-coded to "a" — subclass never varies. */
    protected string $tag = 'a';

    /** Convenience: href + content in one call.
     *
     * @param string $href    the href attribute value (auto-escaped).
     * @param string $content inner text of the anchor (auto-escaped).
     * @return static         a fully-configured A instance. */
    public static function to(string $href, string $content = ''): static
    {
        $a = new static();
        return $a->setAttribute('href', $href)->setContent($content);
    }
}
