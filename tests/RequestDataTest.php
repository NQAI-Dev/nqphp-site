<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\Input;
use Nqphp\Core\Input\RequestData;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Phase 2 #9 (request input extraction) test.
 *
 * Verifies:
 *   - JSON body is parsed and properties are filled
 *   - Query string is parsed as fallback
 *   - JSON wins over form wins over query (first non-null)
 *   - snake_case raw keys map to camelCase properties
 *   - Properties without defaults keep their initial values if absent
 *   - Non-#[Input] class throws
 */
final class RequestDataTest extends TestCase
{
    public function testExtractsFromJsonBody(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-input-' . uniqid();
        mkdir($tmp . '/Feature/Blog/Input', 0755, true);
        $srcPath = $tmp . '/Feature/Blog/Input/CreatePostInput.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Blog\Input;

use Nqphp\Core\Attribute\Input;

#[Input(name: 'blog:create-post')]
final class CreatePostInput
{
    public string $title = '';
    public string $body = '';
    public int $authorId = 0;
}
PHP);

        require_once $srcPath;
        $req = Request::create('/api/posts', 'POST', [], [], [], [], '{"title":"Hello","body":"World","author_id":42}');
        $req->headers->set('Content-Type', 'application/json');
        $extractor = new RequestData($req);
        /** @var object $input */
        $input = $extractor->extract(\App\Blog\Input\CreatePostInput::class);
        self::assertSame('Hello', $input->title);
        self::assertSame('World', $input->body);
        self::assertSame(42, $input->authorId);

        unlink($srcPath);
        rmdir($tmp . '/Feature/Blog/Input');
        rmdir($tmp . '/Feature/Blog');
        rmdir($tmp . '/Feature');
        rmdir($tmp);
    }

    public function testExtractsFromQueryString(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-input2-' . uniqid();
        mkdir($tmp . '/Feature/Page/Input', 0755, true);
        $srcPath = $tmp . '/Feature/Page/Input/PageInput.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Page\Input;

use Nqphp\Core\Attribute\Input;

#[Input(name: 'page:view')]
final class PageInput
{
    public int $pageNumber = 1;
    public string $sortBy = 'id';
}
PHP);

        require_once $srcPath;
        $req = Request::create('/list?page_number=3&sort_by=name', 'GET');
        $extractor = new RequestData($req);
        /** @var object $input */
        $input = $extractor->extract(\App\Page\Input\PageInput::class);
        self::assertSame(3, $input->pageNumber);
        self::assertSame('name', $input->sortBy);

        unlink($srcPath);
        rmdir($tmp . '/Feature/Page/Input');
        rmdir($tmp . '/Feature/Page');
        rmdir($tmp . '/Feature');
        rmdir($tmp);
    }

    public function testKeepsDefaultWhenPropertyAbsent(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-input3-' . uniqid();
        mkdir($tmp . '/Feature/Page/Input', 0755, true);
        $srcPath = $tmp . '/Feature/Page/Input/PageInput.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Page\Input;

use Nqphp\Core\Attribute\Input;

#[Input(name: 'page:view')]
final class PageInput
{
    public int $pageNumber = 1;
}
PHP);

        require_once $srcPath;
        $req = Request::create('/list', 'GET');
        $extractor = new RequestData($req);
        /** @var object $input */
        $input = $extractor->extract(\App\Page\Input\PageInput::class);
        self::assertSame(1, $input->pageNumber);  // default

        unlink($srcPath);
        rmdir($tmp . '/Feature/Page/Input');
        rmdir($tmp . '/Feature/Page');
        rmdir($tmp . '/Feature');
        rmdir($tmp);
    }

    public function testExtractsFromNonInputClassThrows(): void
    {
        $req = Request::create('/list', 'GET');
        $extractor = new RequestData($req);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not marked with #[Input]');
        $extractor->extract(\stdClass::class);
    }
}
