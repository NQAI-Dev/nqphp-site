<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * Raw unescaped HTML wrapper for trusted markup.
 */
class Raw extends AbstractTag
{
    private string $rawHtml;

    public function __construct(string $rawHtml)
    {
        $this->rawHtml = $rawHtml;
    }

    public function toHtml(): string
    {
        return $this->rawHtml;
    }
}
