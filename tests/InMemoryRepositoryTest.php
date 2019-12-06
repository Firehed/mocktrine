<?php
declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\ORMException;
use Error;
use TypeError;
use UnexpectedValueException;

/**
 * @coversDefaultClass Firehed\Mocktrine\InMemoryRepository
 * @covers ::<protected>
 * @covers ::<private>
 */
class InMemoryRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @covers ::__construct */
    public function testConstruct(): void
    {
        $repo = new InMemoryRepository(Entities\User::class);
        /**
         * @psalm-suppress RedundantCondition
         */
        $this->assertInstanceOf(ObjectRepository::class, $repo);
    }

    /** @covers ::getClassName */
    public function testGetClassName(): void
    {
        $repo = new InMemoryRepository(Entities\User::class);
        $this->assertSame(Entities\User::class, $repo->getClassName());
    }

    /** @covers ::manage */
    public function testManageAcceptsOwnClass(): void
    {
        $repo = new InMemoryRepository(Entities\User::class);
        $this->assertNull($repo->manage(new Entities\User('1@example.com', 'last', 1)));
    }

    /** @covers ::manage */
    public function testManageRejectsOtherClasses(): void
    {
        $repo = new InMemoryRepository(Entities\User::class);
        $this->expectException(TypeError::class);
        /**
         * @psalm-suppress InvalidArgument
         */
        $repo->manage(new Entities\Group());
    }

    /** @covers ::findBy */
    public function testSimpleFindBy(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy(['lastName' => 'last']);
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        usort($results, function ($a, $b) {
            return $a->getEmail() <=> $b->getEmail();
        });
        $this->assertSame('1@example.com', $results[0]->getEmail());
        $this->assertSame('2@example.com', $results[1]->getEmail());
    }

    /** @covers ::findBy */
    public function testFindByWithArrayValue(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([
            'email' => ['1@example.com', '3@example.com'],
        ]);
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        usort($results, function ($a, $b) {
            return $a->getEmail() <=> $b->getEmail();
        });
        $this->assertSame('1@example.com', $results[0]->getEmail());
        $this->assertSame('3@example.com', $results[1]->getEmail());
    }

    /** @covers ::findBy */
    public function testFindByWithSorting(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['lastName' => 'ASC', 'email' => 'DESC']);
        $this->assertCount(5, $results);
        $this->assertSame('2@example.com', $results[0]->getEmail());
        $this->assertSame('1@example.com', $results[1]->getEmail());
        $this->assertSame('5@example.com', $results[2]->getEmail());
        $this->assertSame('4@example.com', $results[3]->getEmail());
        $this->assertSame('3@example.com', $results[4]->getEmail());
    }

    /** @covers ::findBy */
    public function testFindByWithLimit(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 2);
        $this->assertCount(2, $results);
        $this->assertSame('1@example.com', $results[0]->getEmail());
        $this->assertSame('2@example.com', $results[1]->getEmail());
    }

    /** @covers ::findBy */
    public function testFindByWithLimitExceedingSet(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 10);
        $this->assertCount(5, $results);
        $this->assertSame('1@example.com', $results[0]->getEmail());
        $this->assertSame('2@example.com', $results[1]->getEmail());
        $this->assertSame('3@example.com', $results[2]->getEmail());
        $this->assertSame('4@example.com', $results[3]->getEmail());
        $this->assertSame('5@example.com', $results[4]->getEmail());
    }

    /** @covers ::findBy */
    public function testFindByWithOffset(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], null, 2);
        $this->assertCount(3, $results);
        $this->assertSame('3@example.com', $results[0]->getEmail());
        $this->assertSame('4@example.com', $results[1]->getEmail());
        $this->assertSame('5@example.com', $results[2]->getEmail());
    }

    /** @covers ::findBy */
    public function testFindByWithOffsetExceedingSet(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], null, 10);
        $this->assertCount(0, $results);
    }

    /** @covers ::findBy */
    public function testFindByWithLimitAndOffset(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 2, 2);
        $this->assertCount(2, $results);
        $this->assertSame('3@example.com', $results[0]->getEmail());
        $this->assertSame('4@example.com', $results[1]->getEmail());
    }

    /** @covers ::findBy */
    public function testFindByWithLimitAndOffsetExceedingSet(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 2, 4);
        $this->assertCount(1, $results);
        $this->assertSame('5@example.com', $results[0]->getEmail());
    }

    /** @covers ::findBy */
    public function testFindByWithLimitAndOffsetTotallyExceedingSet(): void
    {
        $repo = $this->getFixture();
        $results = $repo->findBy([], ['email' => 'ASC'], 2, 10);
        $this->assertCount(0, $results);
    }


    /** @covers ::findOneBy */
    public function testFindOneByWithNoMatches(): void
    {
        $repo = $this->getFixture();
        $this->assertNull($repo->findOneBy(['email' => 'notfound@example.com']));
    }

    /** @covers ::findOneBy */
    public function testFindOneByWithOneMatch(): void
    {
        $repo = $this->getFixture();
        $entity = $repo->findOneBy(['email' => '3@example.com']);
        $this->assertInstanceOf(Entities\User::class, $entity);
        $this->assertSame('3@example.com', $entity->getEmail());
    }

    /** @covers ::findOneBy */
    public function testFindOneByWithMultipleMatches(): void
    {
        $repo = $this->getFixture();
        $entity = $repo->findOneBy(['lastName' => 'last']);
        $this->assertInstanceOf(Entities\User::class, $entity);
        $this->assertSame('last', $entity->getLastName());
    }

    /** @covers ::find */
    public function testFindWhereIdExists(): void
    {
        $repo = $this->getFixture();
        $entity = $repo->find(3);
        $this->assertNotNull($entity);
        $this->assertSame('3@example.com', $entity->getEmail());
    }

    /** @covers ::find */
    public function testFindWhereIdDoesNotExist(): void
    {
        $repo = $this->getFixture();
        $this->assertNull($repo->find(20));
    }

    /** @covers ::findAll */
    public function testFindAll(): void
    {
        $repo = $this->getFixture();
        $all = $repo->findAll();
        $this->assertCount(5, $all);
    }

    // Tests for internals

    /** @covers ::getIdField */
    public function testGetIdFieldTypical(): void
    {
        $repo = new InMemoryRepository(Entities\User::class);
        $this->assertSame('id', $repo->getIdField());
    }

    /** @covers ::getIdField */
    public function testGetIdFieldAtypicalField(): void
    {
        $repo = new InMemoryRepository(Entities\Node::class);
        $this->assertSame('nodeId', $repo->getIdField());
    }

    /** @covers ::isIdGenerated */
    public function testIdIsGeneratedTrue(): void
    {
        $repo = new InMemoryRepository(Entities\User::class);
        $this->assertTrue($repo->isIdGenerated());
    }

    /** @covers ::isIdGenerated */
    public function testIdIsGeneratedFalse(): void
    {
        $repo = new InMemoryRepository(Entities\Node::class);
        $this->assertFalse($repo->isIdGenerated());
    }

    // General checks not specific to any method

    public function testReturnedObjectDoesNotHaveReflectedPropertiesExposed(): void
    {
        $repo = new InMemoryRepository(Entities\User::class);
        $user = new Entities\User('user@example.com', 'lastname');
        $repo->manage($user);
        $found = $repo->findAll();
        $this->assertCount(1, $found);
        $foundUser = current($found);
        $this->expectException(Error::class);
        /**
         * @psalm-suppress InaccessibleProperty
         */
        $foundUser->lastName = 'asdf';
    }

    /** @covers ::findBy */
    public function testCriteriaUsesFieldNamesNotColumnNames(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy(['last_name' => 'last']);
    }

    /** @covers ::findBy */
    public function testCriteraThrowsIfFieldDoesNotExist(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy(['doesNotExist' => true]);
    }

    /** @covers ::findBy */
    public function testFilteringOnNonColumnFieldThrows(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy(['notAColumn' => 100]);
    }

    /** @covers ::findBy */
    public function testSortingWithInvalidDirectionFieldThrows(): void
    {
        $repo = $this->getFixture();
        $this->expectException(ORMException::class);
        $repo->findBy([], ['email' => 'SOMETHING']);
    }

    /** @covers ::findBy */
    public function testSortingOnNonColumnFieldThrows(): void
    {
        $repo = $this->getFixture();
        $this->expectException(UnexpectedValueException::class);
        $repo->findBy([], ['notAColumn' => 'ASC']);
    }

    /**
     * @return InMemoryRepository<Entities\User>
     */
    private function getFixture(): InMemoryRepository
    {
        $repo = new InMemoryRepository(Entities\User::class);
        $repo->manage(new Entities\User('1@example.com', 'last', 1));
        $repo->manage(new Entities\User('2@example.com', 'last', 2));
        $repo->manage(new Entities\User('3@example.com', 'other', 3));
        $repo->manage(new Entities\User('4@example.com', 'other', 4));
        $repo->manage(new Entities\User('5@example.com', 'other', 5));
        return $repo;
    }
}
