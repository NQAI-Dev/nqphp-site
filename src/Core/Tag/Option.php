<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * <option> HTML element builder — child of <select>. Use Tag::option() rather than
 * instantiating directly.
 *
 * Convenience:
 *   echo Tag::option('red')->setContent('Red');
 *   // <option value="red">Red</option>
 *
 *   echo Tag::option('blue', 'Blue', selected: true);
 *   // <option value="blue" selected="selected">Blue</option>
 */
final class Option extends AbstractTag
{
    /** Tag name is hard-coded to "option" — subclass never varies. */
    public function __construct()
    {
        $this->tag = 'option';
    }

    /** Convenience: value + content + optional selected flag.
     *
     * @param string $value    the `value` attribute (sent to the server).
     * @param string $content  inner text shown to the user.
     * @param bool   $selected whether this option is the default choice.
     * @return static          a fully-configured Option instance. */
    public static function of(string $value, string $content = '', bool $selected = false): static
    {
        $opt = (new static())
            ->setAttribute('value', $value)
            ->setContent($content);
        if ($selected) {
            $opt->setAttribute('selected', 'selected');
        }
        return $opt;
    }
}
