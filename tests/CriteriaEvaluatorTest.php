<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Firehed\Mocktrine\Entities\GrabBag;
use PHPUnit\Framework\Attributes\CoversClass;
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

    public function testIsNull(): void
    {
        $this->markTestSkipped('Need nullable fields first');
    }

    public function testIn(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->in('strField', ['hello', 'goodbye']));

        $this->assertCriteriaReturnsIndexes($crit, 0, 4, 6);
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

    public function testMemberOf(): void
    {
        $this->markTestSkipped('MEMBER_OF is not currently supported');
        // test value is in entity's array
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
}
