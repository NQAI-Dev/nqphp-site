<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/** <hr> HTML element builder. Use Tag::hr() rather than instantiating directly. */
final class Hr extends AbstractTag
{
    /** Tag name is hard-coded to "hr" — subclass never varies. */
    public function __construct()
    {
        $this->tag = 'hr';
        $this->selfClosing = true;
    }
}
