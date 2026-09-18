<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * <select> HTML element builder. Use Tag::select() rather than instantiating directly.
 *
 * Convenience:
 *   $select = Tag::select('color');
 *   $select->appendChild(Tag::option('red')->setContent('Red'));
 *   $select->appendChild(Tag::option('blue')->setContent('Blue'));
 *   echo $select;
 *
 *   // or, via fluent helper:
 *   echo Tag::select('color')
 *       ->addOption('red', 'Red')
 *       ->addOption('blue', 'Blue', true);
 */
final class Select extends AbstractTag
{
    /** Tag name is hard-coded to "select" — subclass never varies. */
    public function __construct()
    {
        $this->tag = 'select';
    }

    /** Convenience: name + first option. Chain addOption() calls to add more.
     *
     * @param string $name      the `name` attribute (used by forms).
     * @param string $firstValue  value of the first option.
     * @param string $firstContent label shown for the first option.
     * @param bool   $firstSelected whether the first option is the default.
     * @return static            a fully-configured Select instance with the first option pre-attached. */
    public static function of(string $name, string $firstValue = '', string $firstContent = '', bool $firstSelected = false): static
    {
        $select = (new static())->setAttribute('name', $name);
        if ($firstValue !== '' || $firstContent !== '') {
            $select->appendChild(Option::of($firstValue, $firstContent, $firstSelected));
        }
        return $select;
    }

    /** Fluent option-adding helper: equivalent to appendChild(Tag::option(...)).
     *
     * @param string $value    the `value` attribute for the new option.
     * @param string $content  inner text for the new option.
     * @param bool   $selected whether the new option is selected.
     * @return static          this Select instance, for chaining. */
    public function addOption(string $value, string $content = '', bool $selected = false): static
    {
        return $this->appendChild(Option::of($value, $content, $selected));
    }
}
