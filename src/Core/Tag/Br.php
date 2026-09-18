<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/** <br> HTML element builder. Use Tag::br() rather than instantiating directly. */
final class Br extends AbstractTag
{
    /** Tag name is hard-coded to "br" — subclass never varies. */
    public function __construct()
    {
        $this->tag = 'br';
        $this->selfClosing = true;
    }
}
