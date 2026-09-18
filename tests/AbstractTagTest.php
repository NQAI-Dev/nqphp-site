<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Tag\A;
use Nqphp\Core\Tag\Div;
use Nqphp\Core\Tag\Span;
use Nqphp\Core\Tag\Tag;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 (HTML tag builder) test.
 *
 * Validates the fluent API + HTML escape semantics across Div,
 * Span, A and the Tag static factory.
 */
final class AbstractTagTest extends TestCase
{
    public function testBasicDivRendering(): void
    {
        $div = (new Div())->setContent('hello');
        self::assertSame('<div>hello</div>', $div->toHtml());
    }

    public function testSetClassRendersClassAttribute(): void
    {
        $div = (new Div())->setClass('container', 'row')->setContent('hi');
        self::assertSame('<div class="container row">hi</div>', $div->toHtml());
    }

    public function testSetClassDeduplicates(): void
    {
        $div = (new Div())->setClass('a', 'b', 'a', 'c', 'b')->setContent('x');
        self::assertSame('<div class="a b c">x</div>', $div->toHtml());
    }

    public function testSetClassMergesWithClassAttribute(): void
    {
        // If the caller sets a class attribute, the setClass() calls
        // merge with it (callers wins on order — last in wins).
        $div = (new Div())
            ->setClass('from-setClass')
            ->setAttribute('class', 'from-attribute')
            ->setContent('x');
        self::assertSame('<div class="from-attribute">x</div>', $div->toHtml());
    }

    public function testAttributesAreRenderedInDeclarationOrder(): void
    {
        $div = (new Div())
            ->setAttribute('id', 'main')
            ->setAttribute('data-x', '1')
            ->setContent('x');
        self::assertSame('<div id="main" data-x="1">x</div>', $div->toHtml());
    }

    public function testToStringMagicMatchesToHtml(): void
    {
        $div = (new Div())->setContent('hello');
        self::assertSame($div->toHtml(), (string) $div);
    }

    public function testAttributeValuesAreEscaped(): void
    {
        $div = (new Div())->setAttribute('title', 'He said "hi" & left')->setContent('x');
        self::assertStringContainsString('&quot;', $div->toHtml());
        self::assertStringContainsString('&amp;', $div->toHtml());
    }

    public function testContentIsEscaped(): void
    {
        $div = (new Div())->setContent('<script>alert(1)</script>');
        $html = $div->toHtml();
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testSpan(): void
    {
        $span = (new Span())->setClass('icon')->setContent('X');
        self::assertSame('<span class="icon">X</span>', $span->toHtml());
    }

    public function testAnchorWithStaticFactory(): void
    {
        $a = A::to('https://example.com', 'click me');
        self::assertSame('<a href="https://example.com">click me</a>', $a->toHtml());
    }

    public function testTagStaticFactory(): void
    {
        self::assertInstanceOf(Div::class, Tag::div());
        self::assertInstanceOf(Span::class, Tag::span());
        self::assertInstanceOf(A::class, Tag::a());
    }
}

class VoidTagsTest extends \PHPUnit\Framework\TestCase
{
    public function testImgRendersAsSelfClosing(): void
    {
        $img = (new \Nqphp\Core\Tag\Img())
            ->setAttribute('src', 'avatar.png')
            ->setAttribute('alt', 'Profile photo');
        self::assertSame('<img src="avatar.png" alt="Profile photo">', $img->toHtml());
    }

    public function testImgToShortcut(): void
    {
        $img = \Nqphp\Core\Tag\Img::to('avatar.png', 'Profile photo');
        self::assertSame('<img src="avatar.png" alt="Profile photo">', $img->toHtml());
    }

    public function testBrRendersAsSelfClosing(): void
    {
        $br = (new \Nqphp\Core\Tag\Br())->setClass('spacer');
        self::assertSame('<br class="spacer">', $br->toHtml());
    }

    public function testHrRendersAsSelfClosing(): void
    {
        $hr = (new \Nqphp\Core\Tag\Hr())->setAttribute('data-section', 'end');
        self::assertSame('<hr data-section="end">', $hr->toHtml());
    }

    public function testTagFactoryHasVoidElements(): void
    {
        self::assertInstanceOf(\Nqphp\Core\Tag\Img::class, \Nqphp\Core\Tag\Tag::img());
        self::assertInstanceOf(\Nqphp\Core\Tag\Br::class, \Nqphp\Core\Tag\Tag::br());
        self::assertInstanceOf(\Nqphp\Core\Tag\Hr::class, \Nqphp\Core\Tag\Tag::hr());
    }
}

class FormInputTagsTest extends \PHPUnit\Framework\TestCase
{
    public function testInputTextShortcut(): void
    {
        $i = \Nqphp\Core\Tag\Input::text('username', 'alice', 'login placeholder');
        self::assertSame(
            '<input type="text" name="username" value="alice" placeholder="login placeholder">',
            $i->toHtml()
        );
    }

    public function testInputEmailRequired(): void
    {
        $i = \Nqphp\Core\Tag\Input::email('email', '', true);
        self::assertSame(
            '<input type="email" name="email" value="" required="required">',
            $i->toHtml()
        );
    }

    public function testInputPassword(): void
    {
        $i = \Nqphp\Core\Tag\Input::password('pwd');
        self::assertSame('<input type="password" name="pwd" value="">', $i->toHtml());
    }

    public function testInputHidden(): void
    {
        $i = \Nqphp\Core\Tag\Input::hidden('csrf', 'tokenvalue');
        self::assertSame('<input type="hidden" name="csrf" value="tokenvalue">', $i->toHtml());
    }

    public function testInputSubmit(): void
    {
        $i = \Nqphp\Core\Tag\Input::submit('Save');
        self::assertSame('<input type="submit" name="" value="Save">', $i->toHtml());
    }

    public function testInputOfGeneric(): void
    {
        $i = \Nqphp\Core\Tag\Input::of('number', 'age', '30', ['min' => '0', 'max' => '120']);
        self::assertSame(
            '<input type="number" name="age" value="30" min="0" max="120">',
            $i->toHtml()
        );
    }

    public function testTextareaShortcut(): void
    {
        $t = \Nqphp\Core\Tag\Textarea::of('body', '<p>Hello</p>', ['rows' => '10', 'cols' => '80']);
        // Content gets escaped — caller passed literal HTML
        self::assertStringContainsString('&lt;p&gt;Hello&lt;/p&gt;', $t->toHtml());
        self::assertStringContainsString('name="body"', $t->toHtml());
        self::assertStringContainsString('rows="10"', $t->toHtml());
        self::assertStringContainsString('cols="80"', $t->toHtml());
    }

    public function testLabelForShortcut(): void
    {
        $l = \Nqphp\Core\Tag\Label::for('username', 'Username');
        self::assertSame('<label for="username">Username</label>', $l->toHtml());
    }

    public function testTagFactoryHasFormTags(): void
    {
        self::assertInstanceOf(\Nqphp\Core\Tag\Input::class, \Nqphp\Core\Tag\Tag::input());
        self::assertInstanceOf(\Nqphp\Core\Tag\Textarea::class, \Nqphp\Core\Tag\Tag::textarea());
        self::assertInstanceOf(\Nqphp\Core\Tag\Label::class, \Nqphp\Core\Tag\Tag::label());
    }
}

class SelectOptionTest extends \PHPUnit\Framework\TestCase
{
    public function testAppendChildRendersChildren(): void
    {
        $div = (new \Nqphp\Core\Tag\Div())
            ->appendChild(\Nqphp\Core\Tag\Tag::span()->setContent('a'))
            ->appendChild(\Nqphp\Core\Tag\Tag::span()->setContent('b'));
        self::assertSame('<div><span>a</span><span>b</span></div>', $div->toHtml());
    }

    public function testSetChildrenOverridesAppended(): void
    {
        $div = (new \Nqphp\Core\Tag\Div())
            ->appendChild(\Nqphp\Core\Tag\Tag::span()->setContent('a'))
            ->setChildren('<i>raw</i>');
        // setChildren REPLACES appended children (raw HTML is trusted).
        self::assertSame('<div><i>raw</i></div>', $div->toHtml());
    }

    public function testContentAndChildrenConcatenate(): void
    {
        // explicit setContent() + appended children both render.
        $div = (new \Nqphp\Core\Tag\Div())
            ->setContent('before')
            ->appendChild(\Nqphp\Core\Tag\Tag::span()->setContent('middle'))
            ->setContent('after');
        // Note: setContent replaces the content field, so only 'after'
        // survives — appended child still renders.
        self::assertSame('<div>after<span>middle</span></div>', $div->toHtml());
    }

    public function testOptionShortcut(): void
    {
        $o = \Nqphp\Core\Tag\Option::of('red', 'Red');
        self::assertSame('<option value="red">Red</option>', $o->toHtml());
    }

    public function testOptionSelectedShortcut(): void
    {
        $o = \Nqphp\Core\Tag\Option::of('blue', 'Blue', true);
        self::assertSame('<option value="blue" selected="selected">Blue</option>', $o->toHtml());
    }

    public function testSelectWithMultipleOptions(): void
    {
        $s = \Nqphp\Core\Tag\Select::of('color', 'red', 'Red', true)
            ->addOption('green', 'Green')
            ->addOption('blue', 'Blue');
        self::assertSame(
            '<select name="color">'
            . '<option value="red" selected="selected">Red</option>'
            . '<option value="green">Green</option>'
            . '<option value="blue">Blue</option>'
            . '</select>',
            $s->toHtml()
        );
    }

    public function testSelectEmpty(): void
    {
        $s = \Nqphp\Core\Tag\Select::of('color');
        self::assertSame('<select name="color"></select>', $s->toHtml());
    }

    public function testOptionEscapesValueAndContent(): void
    {
        $o = \Nqphp\Core\Tag\Option::of('a"b', '<script>x</script>');
        $html = $o->toHtml();
        self::assertStringContainsString('&quot;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testTagFactoryHasSelectAndOption(): void
    {
        self::assertInstanceOf(\Nqphp\Core\Tag\Select::class, \Nqphp\Core\Tag\Tag::select());
        self::assertInstanceOf(\Nqphp\Core\Tag\Option::class, \Nqphp\Core\Tag\Tag::option());
    }
}
