<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * <textarea> HTML element builder. Use Tag::textarea() rather than instantiating directly.
 *
 * Convenience:
 *   echo Textarea::of('body', $post->body, ['rows' => '10', 'cols' => '80']);
 */
final class Textarea extends AbstractTag
{
    /** Tag name is hard-coded to "textarea" — subclass never varies. */
    protected string $tag = 'textarea';

    /** Convenience: name, content, plus any other attrs.
     *
     * @param string              $name    the `name` attribute (used by forms).
     * @param string              $content initial content (auto-escaped).
     * @param array<string,string> $extra   additional attributes (rows, cols, required, placeholder, ...).
     * @return static                    a fully-configured Textarea instance. */
    public static function of(string $name, string $content = '', array $extra = []): static
    {
        $ta = (new static())
            ->setAttribute('name', $name)
            ->setContent($content);
        foreach ($extra as $k => $v) {
            $ta->setAttribute((string) $k, (string) $v);
        }
        return $ta;
    }
}
