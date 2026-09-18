<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

final class Span extends AbstractTag
{
    public function __construct()
    {
        $this->tag = 'span';
    }
}
