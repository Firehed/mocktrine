<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use BadMethodCallException;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\Value;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\ClassMetadata;
use DomainException;
use Firehed\Mocktrine\Entities\GrabBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CriteriaEvaluator::class)]
#[Small]
class CriteriaEvaluatorTest extends \PHPUnit\Framework\TestCase
{
    /** @var InMemoryRepository<GrabBag> */
    private InMemoryRepository $repo;

    /** @var GrabBag[] */
    private array $entities;

    public function setUp(): void
    {
        $em = new InMemoryEntityManager(new AttributeDriver(['.']));
        $repo = $em->getRepository(GrabBag::class);
        $this->entities = [
            new GrabBag(true, 30, 'hello', new DateTimeImmutable(), 'abc'),
            new GrabBag(true, 30.5, 'hi', new DateTimeImmutable(), 'xyz'),
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
        $this->assertCount(count($expected), $actual);
        // Can't use assertEqualsCanonicalizing due to its internals. If it's
        // changed or assertSameCanonicalizing is added, this should be able to
        // use it instead.
        foreach ($expected as $entity) {
            $this->assertContains(
                $entity,
                $actual,
                'Expected entity missing',
            );
        }
        // This is intended to guard against duplicates in expected causing an
        // extra in actual permitting the count to match.
        foreach ($actual as $entity) {
            $this->assertContains(
                $entity,
                $expected,
                'Unexpected entity found',
            );
        }
    }

    public function testEqBool(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('boolField', true));

        $this->assertCriteriaReturnsIndexes($crit, 0, 1, 2, 3, 4);
    }

    public function testEqFloat(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('floatField', 30.5));

        $this->assertCriteriaReturnsIndexes($crit, 1);
    }

    public function testEqIntForFloat(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 0, 8, 9);
    }

    public function testNeqBool(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->neq('boolField', true));

        $this->assertCriteriaReturnsIndexes($crit, 5, 6, 7, 8, 9);
    }

    public function testNeqFloat(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->neq('floatField', 30.5));

        $this->assertCriteriaReturnsIndexes($crit, 0, 2, 3, 4, 5, 6, 7, 8, 9);
    }

    public function testNeqIntForFloat(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->neq('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 1, 2, 3, 4, 5, 6, 7);
    }

    public function testLt(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->lt('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 2, 4, 5, 6);
    }

    public function testLte(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->lte('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 0, 2, 4, 5, 6, 8, 9);
    }

    public function testGt(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->gt('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 1, 3, 7);
    }

    public function testGte(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->gte('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 0, 1, 3, 7, 8, 9);
    }

    /**
     * Mirrors the SQL the ORM generates: `= null` and `<> null` become
     * IS [NOT] NULL, a null in an IN list adds `OR ... IS NULL`, and any other
     * comparison against a NULL column is never true.
     *
     * @return array<string, array{Expression, int[]}>
     */
    public static function nullColumnComparisons(): array
    {
        $expr = Criteria::expr();
        $f = 'nullableStrField';
        $nullRows = [2, 3, 4, 5, 6, 7, 8, 9];
        return [
            'eq null' => [$expr->eq($f, null), $nullRows],
            'neq null' => [$expr->neq($f, null), [0, 1]],
            'neq value' => [$expr->neq($f, 'abc'), [1]],
            'in' => [$expr->in($f, ['abc']), [0]],
            'in with null' => [$expr->in($f, ['abc', null]), [0, ...$nullRows]],
            'notIn' => [$expr->notIn($f, ['abc']), [1]],
            'lt' => [$expr->lt($f, 'zzz'), [0, 1]],
            'contains' => [$expr->contains($f, ''), [0, 1]],
            'startsWith' => [$expr->startsWith($f, ''), [0, 1]],
            'endsWith' => [$expr->endsWith($f, ''), [0, 1]],
        ];
    }

    /**
     * @param int[] $indexes
     */
    #[DataProvider('nullColumnComparisons')]
    public function testNullColumn(Expression $expr, array $indexes): void
    {
        $this->assertCriteriaReturnsIndexes(Criteria::create()->where($expr), ...$indexes);
    }

    public function testIn(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->in('strField', ['hello', 'goodbye']));

        $this->assertCriteriaReturnsIndexes($crit, 0, 4, 6);
    }

    public function testInIntForFloat(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->in('floatField', [30, 800]));

        $this->assertCriteriaReturnsIndexes($crit, 0, 3, 8, 9);
    }

    public function testNotInIntForFloat(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->notIn('floatField', [30, 800]));

        $this->assertCriteriaReturnsIndexes($crit, 1, 2, 4, 5, 6, 7);
    }

    public function testNotIn(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->notIn('strField', ['hello', 'goodbye']));

        $this->assertCriteriaReturnsIndexes($crit, 1, 2, 3, 5, 7, 8, 9);
    }

    public function testContains(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->contains('strField', 'oo'));

        $this->assertCriteriaReturnsIndexes($crit, 2, 4, 7, 8);
    }

    public function testMemberOfIsUnsupported(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->memberOf('strField', 'hello'));

        $this->expectException(DomainException::class);
        $this->repo->matching($crit);
    }

    public function testNotIsUnsupported(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->not($expr->eq('boolField', true)));

        $this->expectException(DomainException::class);
        $this->repo->matching($crit);
    }

    public function testValueExpressionIsUnsupported(): void
    {
        $crit = Criteria::create()
            ->where(new Value(true));

        $this->expectException(BadMethodCallException::class);
        $this->repo->matching($crit);
    }

    public function testStartsWith(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->startsWith('strField', 'h'));

        $this->assertCriteriaReturnsIndexes($crit, 0, 1, 5, 6, 7, 9);
    }

    public function testEndsWith(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->endsWith('strField', 'bye'));

        $this->assertCriteriaReturnsIndexes($crit, 3, 4, 7);
    }

    public function testAndWhere(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('boolField', true))
            ->andWhere($expr->gt('floatField', 30));

        $this->assertCriteriaReturnsIndexes($crit, 1, 3);
    }

    public function testOrWhere(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('boolField', true))
            ->orWhere($expr->eq('strField', 'hello, goodbye'));

        $this->assertCriteriaReturnsIndexes($crit, 0, 1, 2, 3, 4, 7);
    }

    public function testCombinationAndOr(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('boolField', true))
            ->andWhere($expr->orX(
                $expr->lt('floatField', 30),
                $expr->gt('floatField', 30)
            ));

        $this->assertCriteriaReturnsIndexes($crit, 1, 2, 3, 4);
    }

    public function testOnlyOrderBy(): void
    {
        $crit = Criteria::create()
            ->orderBy([
                'floatField' => Criteria::DESC,
            ]);

        $this->assertCriteriaReturnsIndexes($crit, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
    }

    public function testOnlyLimits(): void
    {
        $crit = Criteria::create()
            ->orderBy(['floatField' => Criteria::ASC])
            ->setFirstResult(2)
            ->setMaxResults(5);

        $this->assertCriteriaReturnsIndexes($crit, 4, 2, 0, 8, 9);
    }

    public function testZeroMaxResults(): void
    {
        $crit = Criteria::create()
            ->setMaxResults(0);

        $this->assertCriteriaReturnsIndexes($crit);
    }
}
