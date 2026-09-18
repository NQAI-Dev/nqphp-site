<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * <input> HTML element builder. Use Tag::input() rather than instantiating directly.
 *
 * Convenience:
 *   echo Input::text('username', 'alice', placeholder: 'login');
 *   echo Input::email('email', '', required: true);
 *   echo Input::password('pwd');
 *   echo Input::hidden('csrf_token', '...');
 */
final class Input extends AbstractTag
{
    /** Tag name is hard-coded to "input" — subclass never varies. */
    public function __construct()
    {
        $this->tag = 'input';
        $this->selfClosing = true;
    }

    /** Generic constructor: type, name, value, plus any other attrs.
     *
     * @param string              $type  HTML5 input type (text, email, password, hidden, number, submit, ...).
     * @param string              $name  the `name` attribute (used by forms).
     * @param string              $value the `value` attribute.
     * @param array<string,string> $extra additional attributes (placeholder, required, maxlength, ...).
     * @return static                  a fully-configured Input instance. */
    public static function of(string $type, string $name, string $value = '', array $extra = []): static
    {
        $input = (new static())
            ->setAttribute('type', $type)
            ->setAttribute('name', $name)
            ->setAttribute('value', $value);
        foreach ($extra as $k => $v) {
            $input->setAttribute((string) $k, (string) $v);
        }
        return $input;
    }

    /** @return static */
    public static function text(string $name, string $value = '', string $placeholder = ''): static
    {
        $extra = $placeholder === '' ? [] : ['placeholder' => $placeholder];
        return self::of('text', $name, $value, $extra);
    }

    /** @return static */
    public static function email(string $name, string $value = '', bool $required = false): static
    {
        $extra = $required ? ['required' => 'required'] : [];
        return self::of('email', $name, $value, $extra);
    }

    /** @return static */
    public static function password(string $name, string $value = ''): static
    {
        return self::of('password', $name, $value);
    }

    /** @return static */
    public static function hidden(string $name, string $value = ''): static
    {
        return self::of('hidden', $name, $value);
    }

    /** @return static */
    public static function submit(string $value = 'Submit'): static
    {
        return self::of('submit', '', $value);
    }
}
