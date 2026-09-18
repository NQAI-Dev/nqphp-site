<?php

declare(strict_types=1);

namespace Nqphp\Core\Tag;

/**
 * Base class for HTML tag builders.
 *
 * Subclasses set `$tag` (the element name) and `$selfClosing` (true
 * for void elements like <br>, <hr>, <img>). All other configuration
 * goes through the fluent setters — they all return `static` so
 * subclasses inherit the fluent chain.
 *
 * Output is HTML-escaped. Attribute values and content go through
 * htmlspecialchars() before being concatenated, so caller-supplied
 * strings can't escape into the markup.
 *
 * Example:
 *
 *   echo new Div()
 *       ->setClass('container', 'row')
 *       ->setAttribute('id', 'main')
 *       ->setContent('hello');
 *
 *   // <div class="container row" id="main">hello</div>
 *
 * Or via the static factory:
 *
 *   echo Tag::div()->setContent('hello');
 */
abstract class AbstractTag
{
    /** @var string Element name (set by subclass) */
    protected string $tag = '';

    /** @var bool Whether this is a void element (no closing tag) */
    protected bool $selfClosing = false;

    /** @var array<string, string> attribute map (lowercased keys) */
    protected array $attrs = [];

    /** @var list<string> CSS class names (preserved in declaration order) */
    protected array $classes = [];

    protected string $content = '';

    /** @var string Rendered child HTML (concatenation of appendChild calls). */
    protected string $children = '';

    public function __construct(array $attributes = [], string|self|array $children = [])
    {
        $this->setAttributes($attributes);
        
        if (is_array($children)) {
            foreach ($children as $child) {
                if (is_string($child)) {
                    $this->children .= self::escape($child);
                } elseif ($child instanceof self) {
                    $this->appendChild($child);
                }
            }
        } elseif (is_string($children)) {
            $this->setContent($children);
        } elseif ($children instanceof self) {
            $this->appendChild($children);
        }
    }


    /** Append a child tag. The child's rendered HTML becomes part of
     *  this tag's content. Useful for <select><option>...</option></select>,
     *  <ul><li>...</li></ul>, and similar nested structures.
     *
     * @param self $child the child tag (rendered via toHtml()).
     * @return static    this tag instance, for fluent chaining. */
    public function appendChild(self $child): static
    {
        $this->children .= $child->toHtml();
        return $this;
    }

    /** Replace the entire child list with raw HTML. Useful for
     *  hand-rendered children (e.g. when reading from a template).
     *
     * @param string $html pre-rendered child HTML.
     * @return static     this tag instance. */
    public function setChildren(string $html): static
    {
        $this->children = $html;
        return $this;
    }

    /** Set a single attribute. Replaces existing value if the key
     *  was set before. Attribute names are stored as-given (caller
     *  controls case — most HTML5 attribute names are lowercase).
     *
     * @param string $name  Attribute name (e.g. "id", "data-x").
     * @param string $value Attribute value (auto-escaped on render).
     * @return static       The same tag instance, for fluent chaining. */
    public function setAttribute(string $name, string $value): static
    {
        $this->attrs[$name] = $value;
        return $this;
    }

    /** Set many attributes at once. Useful for unpacking $_POST
     *  data when building form-input attributes.
     *
     * @param array<string, string> $attrs attribute map (key → value).
     * @return static the same tag instance. */
    public function setAttributes(array $attrs): static
    {
        foreach ($attrs as $name => $value) {
            $this->attrs[(string) $name] = (string) $value;
        }
        return $this;
    }

    /** Add CSS class names. Multiple invocations accumulate
     *  (preserving order); duplicate names are deduplicated.
     *
     * @param string ...$names CSS class names to add.
     * @return static         the same tag instance. */
    public function setClass(string ...$names): static
    {
        foreach ($names as $name) {
            if (!\in_array($name, $this->classes, true)) {
                $this->classes[] = $name;
            }
        }
        return $this;
    }

    /** Replace the entire class list.
     *
     * @param string[] $names new class list (replaces any existing).
     * @return static         the same tag instance. */
    public function setClasses(array $names): static
    {
        $this->classes = [];
        return $this->setClass(...$names);
    }

    /** Set the inner content. Pass-through escape — caller is
     *  responsible for any HTML they want embedded (e.g. from a
     *  template engine). Plain text is auto-escaped.
     *
     * @param string $content inner HTML/text (auto-escaped on render).
     * @return static        the same tag instance. */
    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /** Render the tag. HTML-escapes attribute values and content.
     *
     * @return string HTML string (`<tag ...>content</tag>` for paired
     *                tags, `<tag ...>` for void/self-closing tags). */
    public function toHtml(): string
    {
        $attrParts = [];

        // class — special, since it's a list and we merge it with
        // any caller-set class= attribute (latter wins on collisions).
        $classes = $this->classes;
        if (isset($this->attrs['class'])) {
            $classes = array_merge($classes, preg_split('/\\s+/', trim($this->attrs['class'])));
            $classes = array_values(array_unique(array_filter($classes, 'strlen')));
            unset($this->attrs['class']);
        }
        if (\count($classes) > 0) {
            $attrParts[] = 'class="' . self::escape(implode(' ', $classes)) . '"';
        }

        foreach ($this->attrs as $name => $value) {
            $attrParts[] = $name . '="' . self::escape($value) . '"';
        }

        $attrString = \count($attrParts) > 0 ? ' ' . implode(' ', $attrParts) : '';

        if ($this->selfClosing) {
            return '<' . $this->tag . $attrString . '>';
        }
        // Inner content = explicit setContent() + appended children.
        // Child HTML is rendered as-is (children escape themselves via
        // their own toHtml() calls), so we don't re-escape here.
        $inner = self::escape($this->content) . $this->children;
        return '<' . $this->tag . $attrString . '>' . $inner . '</' . $this->tag . '>';
    }

    /** PHP's __toString — useful for `echo Tag::div();`
     *
     * @return string same as toHtml(). */
    public function __toString(): string
    {
        return $this->toHtml();
    }

    private static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
