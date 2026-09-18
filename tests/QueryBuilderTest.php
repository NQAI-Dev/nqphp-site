<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Entity\Driver\InMemoryDriver;
use Nqphp\Core\Entity\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for QueryBuilder — fluent query DSL over DriverInterface.
 *
 * Uses InMemoryDriver so the tests run without a real SQLite file.
 */
final class QueryBuilderTest extends TestCase
{
    private InMemoryDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new InMemoryDriver();
        // Seed 10 rows in the 'article' entity store.
        foreach (range(1, 10) as $i) {
            $this->driver->persist('article', [
                'id'         => $i,
                'title'      => "Article $i",
                'status'     => $i % 2 === 0 ? 'published' : 'draft',
                'views'      => $i * 10,
                'created_at' => sprintf('2024-01-%02d', $i),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function testForReturnsQueryBuilderInstance(): void
    {
        $qb = QueryBuilder::for('article', $this->driver);
        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }

    public function testFreshBuilderHasNullLimitAndOffset(): void
    {
        $qb = QueryBuilder::for('article', $this->driver);
        $this->assertNull($qb->getLimit());
        $this->assertNull($qb->getOffset());
    }

    // -------------------------------------------------------------------------
    // fetch() — returns all matching rows
    // -------------------------------------------------------------------------

    public function testFetchWithNoCriteriaReturnsAllRows(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)->fetch();
        $this->assertCount(10, $rows);
    }

    public function testFetchWithExactCriteria(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->where('status', 'published')
            ->fetch();

        $this->assertCount(5, $rows);
        foreach ($rows as $row) {
            $this->assertSame('published', $row['status']);
        }
    }

    public function testFetchWithMultipleCriteriaCombinedAsAnd(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->where('status', 'published')
            ->where('views', ['>=' => 60])
            ->fetch();

        // published rows: 2,4,6,8,10 — views 20,40,60,80,100 — views>=60: 6,8,10
        $this->assertCount(3, $rows);
    }

    // -------------------------------------------------------------------------
    // Operator shorthands
    // -------------------------------------------------------------------------

    public function testWhereIn(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->whereIn('id', [1, 3, 5])
            ->fetch();

        $this->assertCount(3, $rows);
        $ids = array_column($rows, 'id');
        sort($ids);
        $this->assertSame([1, 3, 5], $ids);
    }

    public function testWhereBetween(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->whereBetween('views', 30, 70)
            ->fetch();

        // views 10,20,30,40,50,60,70,80,90,100 → between 30..70: 30,40,50,60,70 = 5 rows
        $this->assertCount(5, $rows);
    }

    public function testWhereLike(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->whereLike('created_at', '2024-01-0%')
            ->fetch();

        // '2024-01-01' through '2024-01-09' = 9 rows
        $this->assertCount(9, $rows);
    }

    // -------------------------------------------------------------------------
    // orderBy()
    // -------------------------------------------------------------------------

    public function testOrderByDescending(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->orderBy('views', 'desc')
            ->fetch();

        $views = array_column($rows, 'views');
        $this->assertSame(array_reverse(range(10, 100, 10)), $views);
    }

    public function testOrderByAscendingIsDefault(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->orderBy('views')
            ->fetch();

        $views = array_column($rows, 'views');
        $this->assertSame(range(10, 100, 10), $views);
    }

    // -------------------------------------------------------------------------
    // limit() and offset()
    // -------------------------------------------------------------------------

    public function testLimit(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->orderBy('id')
            ->limit(3)
            ->fetch();

        $this->assertCount(3, $rows);
        $this->assertSame(1, $rows[0]['id']);
    }

    public function testOffset(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->orderBy('id')
            ->offset(7)
            ->fetch();

        $this->assertCount(3, $rows);
        $this->assertSame(8, $rows[0]['id']);
    }

    public function testLimitAndOffset(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->orderBy('id')
            ->limit(3)
            ->offset(3)
            ->fetch();

        $this->assertCount(3, $rows);
        $ids = array_column($rows, 'id');
        $this->assertSame([4, 5, 6], $ids);
    }

    // -------------------------------------------------------------------------
    // paginate()
    // -------------------------------------------------------------------------

    public function testPaginateFirstPage(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->orderBy('id')
            ->paginate(1, 4)
            ->fetch();

        $this->assertCount(4, $rows);
        $this->assertSame(1, $rows[0]['id']);
    }

    public function testPaginateSecondPage(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->orderBy('id')
            ->paginate(2, 4)
            ->fetch();

        $this->assertCount(4, $rows);
        $this->assertSame(5, $rows[0]['id']);
    }

    public function testPaginateLastIncompletePageReturnsRemainingRows(): void
    {
        $rows = QueryBuilder::for('article', $this->driver)
            ->orderBy('id')
            ->paginate(3, 4)
            ->fetch();

        $this->assertCount(2, $rows); // 10 rows total, page 3 of 4 → 2 remain
    }

    // -------------------------------------------------------------------------
    // fetchOne()
    // -------------------------------------------------------------------------

    public function testFetchOneReturnsFirstMatch(): void
    {
        $row = QueryBuilder::for('article', $this->driver)
            ->where('status', 'draft')
            ->orderBy('id')
            ->fetchOne();

        $this->assertNotNull($row);
        $this->assertSame(1, $row['id']); // first draft row
    }

    public function testFetchOneReturnsNullWhenNoMatch(): void
    {
        $row = QueryBuilder::for('article', $this->driver)
            ->where('status', 'archived')
            ->fetchOne();

        $this->assertNull($row);
    }

    // -------------------------------------------------------------------------
    // count()
    // -------------------------------------------------------------------------

    public function testCountWithNoCriteria(): void
    {
        $count = QueryBuilder::for('article', $this->driver)->count();
        $this->assertSame(10, $count);
    }

    public function testCountWithCriteria(): void
    {
        $count = QueryBuilder::for('article', $this->driver)
            ->where('status', 'draft')
            ->count();

        $this->assertSame(5, $count);
    }

    public function testCountIgnoresLimitAndOffset(): void
    {
        // Even with a limit of 2, count() returns the total matching count.
        $count = QueryBuilder::for('article', $this->driver)
            ->where('status', 'published')
            ->limit(2)
            ->count();

        $this->assertSame(5, $count);
    }

    // -------------------------------------------------------------------------
    // exists()
    // -------------------------------------------------------------------------

    public function testExistsReturnsTrueWhenMatchFound(): void
    {
        $exists = QueryBuilder::for('article', $this->driver)
            ->where('views', ['>=' => 100])
            ->exists();

        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseWhenNoMatch(): void
    {
        $exists = QueryBuilder::for('article', $this->driver)
            ->where('status', 'deleted')
            ->exists();

        $this->assertFalse($exists);
    }

    // -------------------------------------------------------------------------
    // Immutability — cloned builder does not share state
    // -------------------------------------------------------------------------

    public function testBuilderIsImmutable(): void
    {
        $base    = QueryBuilder::for('article', $this->driver)->where('status', 'published');
        $limited = $base->limit(2);

        // $base must not be affected by the limit() call on $limited
        $this->assertNull($base->getLimit());
        $this->assertSame(2, $limited->getLimit());

        // Fetch results differ
        $this->assertCount(5, $base->fetch());
        $this->assertCount(2, $limited->fetch());
    }

    public function testTwoBranchesFromSameBase(): void
    {
        $base = QueryBuilder::for('article', $this->driver)->orderBy('id');

        $first  = $base->where('status', 'draft')->fetch();
        $second = $base->where('status', 'published')->fetch();

        $this->assertCount(5, $first);
        $this->assertCount(5, $second);

        $draftIds     = array_column($first, 'id');
        $publishedIds = array_column($second, 'id');
        $this->assertEmpty(array_intersect($draftIds, $publishedIds));
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    public function testGetCriteriaReturnsAddedConstraints(): void
    {
        $qb = QueryBuilder::for('article', $this->driver)
            ->where('status', 'published')
            ->where('views', ['>=' => 50]);

        $criteria = $qb->getCriteria();
        $this->assertSame('published', $criteria['status']);
        $this->assertSame(['>=' => 50], $criteria['views']);
    }

    public function testGetOrderByReturnsOrderClauses(): void
    {
        $qb = QueryBuilder::for('article', $this->driver)
            ->orderBy('views', 'desc')
            ->orderBy('id', 'asc');

        $this->assertSame(['views' => 'desc', 'id' => 'asc'], $qb->getOrderBy());
    }
}
