<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ObjectRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TypeError;
use UnexpectedValueException;

#[CoversClass(InMemoryRepository::class)]
#[Small]
class InMemoryRepositoryTest extends \PHPUnit\Framework\TestCase
{
    private MappingDriver $driver;

    public function setUp(): void
    {
        $this->driver = new AttributeDriver(['.']);
    }

    public function testConstruct(): void
    {
        $repo = new InMemoryRepository(Entities\User::class, $this->driver);
        /**
         * @psalm-suppress RedundantCondition
         */
        $this->assertInstanceOf(ObjectRepository::class, $repo);
    }

    public function testGetClassName(): void
    {
        $repo = new InMemoryRepository(Entities\User::class, $this->driver);
        $this->assertSame(Entities\User::class, $repo->getClassName());
    }

    public function testManageAcceptsOwnClass(): void
    {
        $repo = new InMemoryRepository(Entities\User::class, $this->driver);
        $repo->manage(new Entities\User('1@example.com', 'last', 1));
        $this->assertTrue(true, 'Should not throw');
    }

    public function testManageRejectsOtherClasses(): void
    {
        $repo = new InMemoryRepository(Entities\User::class, $this->driver);
        $this->expectException(TypeError::class);
        /**
         * @psalm-suppress InvalidArgument
         * @phpstan-ignore-next-line
         */
        $repo->manage(new Entities\Group());
    }

    public function testSimpleFindBy(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy(['lastName' => 'last']);
        $this->assertIsArray($results);
        $this->assertResultSizeAndIndexValidity(2, $results);
        usort($results, function ($a, $b) {
            return $a->getEmail() <=> $b->getEmail();
        });
        $this->assertSame('1@example.com', $results[0]->getEmail());
        $this->assertSame('2@example.com', $results[1]->getEmail());
    }

    public function testCount(): void
    {
        $repo = $this->getFixture();
        self::assertSame(5, $repo->count([]));
        self::assertSame(2, $repo->count(['lastName' => 'last']));
        self::assertSame(3, $repo->count(['lastName' => 'other']));
        self::assertSame(1, $repo->count(['email' => '1@example.com', 'lastName' => 'last']));
        self::assertSame(0, $repo->count(['email' => '1@example.com', 'lastName' => 'other']));
        self::assertSame(1, $repo->count(['email' => '1@example.com']));
        self::assertSame(0, $repo->count(['email' => '6@example.com']));
    }

    public function testFindByWithArrayValue(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([
            'email' => ['1@example.com', '3@example.com'],
        ]);
        $this->assertIsArray($results);
        $this->assertResultSizeAndIndexValidity(2, $results);
        usort($results, function ($a, $b) {
            return $a->getEmail() <=> $b->getEmail();
        });
        $this->assertSame('1@example.com', $results[0]->getEmail());
        $this->assertSame('3@example.com', $results[1]->getEmail());
    }

    public function testFindByWithSorting(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['lastName' => 'ASC', 'email' => 'DESC']);
        $this->assertResultSizeAndIndexValidity(5, $results);
        $this->assertSame('2@example.com', $results[0]->getEmail());
        $this->assertSame('1@example.com', $results[1]->getEmail());
        $this->assertSame('5@example.com', $results[2]->getEmail());
        $this->assertSame('4@example.com', $results[3]->getEmail());
        $this->assertSame('3@example.com', $results[4]->getEmail());
    }

    public function testFindBySortingIsCaseInsensitive(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['lastName' => 'asc', 'email' => 'dEsC']);
        $this->assertResultSizeAndIndexValidity(5, $results);
        $this->assertSame('2@example.com', $results[0]->getEmail());
        $this->assertSame('1@example.com', $results[1]->getEmail());
        $this->assertSame('5@example.com', $results[2]->getEmail());
        $this->assertSame('4@example.com', $results[3]->getEmail());
        $this->assertSame('3@example.com', $results[4]->getEmail());
    }

    public function testFindByWithLimit(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 2);
        $this->assertResultSizeAndIndexValidity(2, $results);
        $this->assertSame('1@example.com', $results[0]->getEmail());
        $this->assertSame('2@example.com', $results[1]->getEmail());
    }

    public function testFindByWithLimitExceedingSet(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 10);
        $this->assertResultSizeAndIndexValidity(5, $results);
        $this->assertSame('1@example.com', $results[0]->getEmail());
        $this->assertSame('2@example.com', $results[1]->getEmail());
        $this->assertSame('3@example.com', $results[2]->getEmail());
        $this->assertSame('4@example.com', $results[3]->getEmail());
        $this->assertSame('5@example.com', $results[4]->getEmail());
    }

    public function testFindByWithOffset(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], null, 2);
        $this->assertResultSizeAndIndexValidity(3, $results);
        $this->assertSame('3@example.com', $results[0]->getEmail());
        $this->assertSame('4@example.com', $results[1]->getEmail());
        $this->assertSame('5@example.com', $results[2]->getEmail());
    }

    public function testFindByWithOffsetExceedingSet(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], null, 10);
        $this->assertResultSizeAndIndexValidity(0, $results);
    }

    public function testFindByWithLimitAndOffset(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 2, 2);
        $this->assertResultSizeAndIndexValidity(2, $results);
        $this->assertSame('3@example.com', $results[0]->getEmail());
        $this->assertSame('4@example.com', $results[1]->getEmail());
    }

    public function testFindByWithLimitAndOffsetExceedingSet(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 2, 4);
        $this->assertResultSizeAndIndexValidity(1, $results);
        $this->assertSame('5@example.com', $results[0]->getEmail());
    }

    public function testFindByWithLimitAndOffsetTotallyExceedingSet(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 2, 10);
        $this->assertResultSizeAndIndexValidity(0, $results);
    }

    public function testFindOneByWithNoMatches(): void
    {
        $repo = $this->getFixture();
        $this->assertNull($repo->findOneBy(['email' => 'notfound@example.com']));
    }

    public function testFindOneByWithOneMatch(): void
    {
        $repo = $this->getFixture();
        $entity = $repo->findOneBy(['email' => '3@example.com']);
        $this->assertInstanceOf(Entities\User::class, $entity);
        $this->assertSame('3@example.com', $entity->getEmail());
    }

    public function testFindOneByWithMultipleMatches(): void
    {
        $repo = $this->getFixture();
        $entity = $repo->findOneBy(['lastName' => 'last']);
        $this->assertInstanceOf(Entities\User::class, $entity);
        $this->assertSame('last', $entity->getLastName());
    }

    public function testFindWhereIdExists(): void
    {
        $repo = $this->getFixture();
        $entity = $repo->find(3);
        $this->assertNotNull($entity);
        $this->assertSame('3@example.com', $entity->getEmail());
    }

    public function testFindWhereIdDoesNotExist(): void
    {
        $repo = $this->getFixture();
        $this->assertNull($repo->find(20));
    }

    public function testFindAll(): void
    {
        $repo = $this->getFixture();
        $all = $repo->findAll();
        $this->assertResultSizeAndIndexValidity(5, $all);
    }

    // Test bad entity handling

    public function testEntityWithNoIdField(): void
    {
        $this->expectException(MappingException::class);
        new InMemoryRepository(Entities\Group::class, $this->driver);
    }

    // Tests for internals

    public function testGetIdFieldTypical(): void
    {
        $repo = new InMemoryRepository(Entities\User::class, $this->driver);
        $this->assertSame('id', $repo->getIdField());
    }

    public function testGetIdFieldAtypicalField(): void
    {
        $repo = new InMemoryRepository(Entities\Node::class, $this->driver);
        $this->assertSame('nodeId', $repo->getIdField());
    }

    public function testIdIsGeneratedTrue(): void
    {
        $repo = new InMemoryRepository(Entities\User::class, $this->driver);
        $this->assertTrue($repo->isIdGenerated());
    }

    public function testIdIsGeneratedFalse(): void
    {
        $repo = new InMemoryRepository(Entities\Node::class, $this->driver);
        $this->assertFalse($repo->isIdGenerated());
    }

    // General checks not specific to any method

    public function testReturnedObjectDoesNotHaveReflectedPropertiesExposed(): void
    {
        $repo = new InMemoryRepository(Entities\User::class, $this->driver);
        $user = new Entities\User('user@example.com', 'lastname');
        $repo->manage($user);
        $found = $repo->findAll();
        $this->assertResultSizeAndIndexValidity(1, $found);
        $foundUser = current($found);
        $this->expectException(Error::class);
        /**
         * @psalm-suppress InaccessibleProperty
         * @phpstan-ignore-next-line
         */
        $foundUser->lastName = 'asdf';
    }

    public function testCriteriaUsesFieldNamesNotColumnNames(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy(['last_name' => 'last']);
    }

    public function testCriteraThrowsIfFieldDoesNotExist(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy(['doesNotExist' => true]);
    }

    public function testFilteringOnNonColumnFieldThrows(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy(['notAColumn' => 100]);
    }

    public function testSortingWithInvalidDirectionFieldThrows(): void
    {
        $repo = $this->getFixture();
        $this->expectException(ORMException::class);
        $repo->findBy([], ['email' => 'SOMETHING']);
    }

    public function testSortingOnNonColumnFieldThrows(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy([], ['notAColumn' => 'ASC']);
    }

    public function testSortingOnNonColumnFieldThrowsWithNoResults(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy(['lastName' => 'noMatch'], ['notAColumn' => 'desc']);
    }

    public function testMatching(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('lastName', 'last'));

        $repo = $this->getFixture();
        $users = $repo->matching($crit)->toArray();

        $this->assertResultSizeAndIndexValidity(2, $users);
        foreach ($users as $user) {
            $this->assertInstanceOf(Entities\User::class, $user);
            $this->assertSame('last', $user->getLastName());
        }
    }

    public function testComplexMatching(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('lastName', 'last'))
            ->orWhere($expr->andX(
                $expr->eq('lastName', 'other'),
                $expr->eq('email', '4@example.com'),
            ));

        $repo = $this->getFixture();
        $users = $repo->matching($crit)->toArray();

        $this->assertResultSizeAndIndexValidity(3, $users);
        $this->assertEqualsCanonicalizing([
            $repo->find(1),
            $repo->find(2),
            $repo->find(4),
        ], $users);
    }

    public function testComplexMatchingWithDuplicates(): void
    {
        $expr = Criteria::expr();
        $crit = Criteria::create()
            ->where($expr->eq('lastName', 'last'))
            ->orWhere($expr->andX(
                $expr->eq('lastName', 'other'),
                $expr->eq('email', '4@example.com'),
            ))
            ->orWhere($expr->eq('id', 4));

        $repo = $this->getFixture();
        $users = $repo->matching($crit)->toArray();

        $this->assertResultSizeAndIndexValidity(3, $users);
        $this->assertEqualsCanonicalizing([
            $repo->find(1),
            $repo->find(2),
            $repo->find(4),
        ], $users);
    }

    /**
     * @return InMemoryRepository<Entities\User>
     */
    private function getFixture(): InMemoryRepository
    {
        $repo = new InMemoryRepository(Entities\User::class, $this->driver);
        $repo->manage(new Entities\User('1@example.com', 'last', 1));
        $repo->manage(new Entities\User('2@example.com', 'last', 2));
        $repo->manage(new Entities\User('3@example.com', 'other', 3));
        $repo->manage(new Entities\User('4@example.com', 'other', 4));
        $repo->manage(new Entities\User('5@example.com', 'other', 5));
        return $repo;
    }

    /**
     * @param mixed[] $results
     */
    private function assertResultSizeAndIndexValidity(int $expectedSize, array $results): void
    {
        $this->assertCount($expectedSize, $results);
        // Check that the results are list-formated and don't have weird
        // indexes or gaps
        for ($i = 0; $i < $expectedSize; $i++) {
            $this->assertArrayHasKey($i, $results);
        }
    }
}
