<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * <label> HTML element builder. Use Tag::label() rather than instantiating directly.
 *
 * Convenience:
 *   echo Label::for('username', 'Username');
 *   // <label for="username">Username</label>
 */
final class Label extends AbstractTag
{
    /** Tag name is hard-coded to "label" — subclass never varies. */
    public function __construct()
    {
        $this->tag = 'label';
    }

    /** Convenience: for-id + label text in one call.
     *
     * @param string $for     the `for` attribute (target input id).
     * @param string $content label text (auto-escaped).
     * @return static        a fully-configured Label instance. */
    public static function for(string $for, string $content = ''): static
    {
        return (new static())
            ->setAttribute('for', $for)
            ->setContent($content);
    }
}
