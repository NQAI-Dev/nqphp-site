<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * <img> HTML element builder. Use Tag::img() rather than instantiating directly.
 *
 * Convenience:
 *   echo Img::to('avatar.png', 'Profile photo');
 *   // <img src="avatar.png" alt="Profile photo">
 */
final class Img extends AbstractTag
{
    /** Tag name is hard-coded to "img" — subclass never varies. */
    public function __construct()
    {
        $this->tag = 'img';
        $this->selfClosing = true;
    }

    /** Convenience: src + alt in one call.
     *
     * @param string $src the src attribute value (auto-escaped).
     * @param string $alt the alt attribute value (auto-escaped, empty string allowed).
     * @return static      a fully-configured Img instance. */
    public static function to(string $src, string $alt = ''): static
    {
        $img = new static();
        return $img
            ->setAttribute('src', $src)
            ->setAttribute('alt', $alt);
    }
}
