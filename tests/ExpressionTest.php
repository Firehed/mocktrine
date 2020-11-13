<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Firehed\Mocktrine\Entities\GrabBag;

/**
 * @coversDefaultClass Firehed\Mocktrine\InMemoryExpressionVisitor
 * @covers ::<protected>
 * @covers ::<private>
 */
class ExpressionTest extends \PHPUnit\Framework\TestCase
{
    /** @var InMemoryRepository<GrabBag> */
    private InMemoryRepository $repo;

    /** @var GrabBag[] */
    private array $entities;

    public function setUp(): void
    {
        $repo = new InMemoryRepository(GrabBag::class);
        $this->entities = [
            new GrabBag(true, 30, 'hello', new DateTimeImmutable()),
            new GrabBag(true, 30.5, 'hi', new DateTimeImmutable()),
            new GrabBag(true, 29.5, 'good', new DateTimeImmutable()),
            new GrabBag(true, 800, 'bye', new DateTimeImmutable()),
            new GrabBag(true, 0, 'goodbye', new DateTimeImmutable()),
            new GrabBag(false, -17, 'hey', new DateTimeImmutable()),
            new GrabBag(false, -3.14, 'hello', new DateTimeImmutable()),
            new GrabBag(false, 42, 'hello, goodbye', new DateTimeImmutable()),
            new GrabBag(false, 30, 'goodbye, hello', new DateTimeImmutable()),
            new GrabBag(false, 30, 'hallå', new DateTimeImmutable()),
        ];

        foreach ($this->entities as $entity) {
            $repo->manage($entity);
        }

        $this->repo = $repo;
    }

    private function assertCriteriaReturnsIndexes(Criteria $criteria, int ...$indexes): void
    {
        $actual = $this->repo->matching($criteria)->toArray();
        $expected = array_map(fn ($i) => $this->entities[$i], $indexes);
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function testEq(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 0, 8, 9);
    }

    public function testGt(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->gt('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 1, 3, 7);
    }

    public function testLt(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->lt('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 2, 4, 5, 6);
    }

    public function testGte(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->gte('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 0, 1, 3, 7, 8, 9);
    }

    public function testLte(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->lte('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 0, 2, 4, 5, 6, 8, 9);
    }

    public function testNeq(): void
    {
    }

    public function testIsNull(): void
    {
    }

    public function testIn(): void
    {
    }

    public function testNotIn(): void
    {
    }

    public function testContains(): void
    {
        // strpos(fieldVal, testVal) !== false
    }

    public function testMemberOf(): void
    {
        // test value is in entity's array
    }

    public function testStartsWith(): void
    {
        // str_starts_with
    }

    public function testEndsWith(): void
    {
        // str_ends_with
    }
}
