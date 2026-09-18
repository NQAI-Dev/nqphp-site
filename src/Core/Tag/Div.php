<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

final class Div extends AbstractTag
{
    public function __construct()
    {
        $this->tag = 'div';
    }
}
