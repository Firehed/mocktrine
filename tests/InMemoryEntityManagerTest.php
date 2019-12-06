<?php
declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Common\Persistence\ObjectRepository;

/**
 * @coversDefaultClass Firehed\Mocktrine\InMemoryEntityManager
 * @covers ::<protected>
 * @covers ::<private>
 */
class InMemoryEntityManagerTest extends \PHPUnit\Framework\TestCase
{
    /** @covers ::find */
    public function testFindWithNoEntity(): void
    {
        $em = new InMemoryEntityManager();
        $this->assertNull($em->find(Entities\User::class, 10));
    }

    /**
     * @covers ::find
     * @covers ::merge
     */
    public function testFindWithPersistedEntity(): void
    {
        $user = new Entities\User('1@example.com', 'last', 10);
        $em = new InMemoryEntityManager();
        $em->merge($user); // This starts managing the entity

        $this->assertSame($user, $em->find(Entities\User::class, 10));
    }

    /**
     * @covers ::getRepository
     */
    public function testGetRepository(): void
    {
        $em = new InMemoryEntityManager();
        $repo = $em->getRepository(Entities\User::class);
        $this->assertInstanceOf(InMemoryRepository::class, $repo);
        $this->assertInstanceOf(ObjectRepository::class, $repo);
        $this->assertSame(Entities\User::class, $repo->getClassName());
    }

    /**
     * @covers ::persist
     * @covers ::flush
     */
    public function testIdIsNotAssignedBeforeFlush(): void
    {
        $user = new Entities\User('1@example.com', 'last');
        $this->assertNull($user->getId(), 'Precheck: Id should not be assigned yet');
        $em = new InMemoryEntityManager();
        $em->persist($user);
        $this->assertNull($user->getId(), 'Id should not be assigned yet');
    }

    /**
     * @covers ::persist
     * @covers ::flush
     */
    public function testIdIsAssignedAfterFlush(): void
    {
        $user = new Entities\User('1@example.com', 'last');
        $this->assertNull($user->getId(), 'Precheck: Id should not be assigned yet');
        $em = new InMemoryEntityManager();
        $em->persist($user);
        $em->flush();
        $this->assertNotNull($user->getId(), 'Id should be assigned');
        $this->assertIsInt($user->getId());
    }

    /**
     * @covers ::persist
     * @covers ::flush
     */
    public function testIdIsNotChangedAfterSecondFlush(): void
    {
        $user = new Entities\User('1@example.com', 'last');
        $this->assertNull($user->getId(), 'Precheck: Id should not be assigned yet');
        $em = new InMemoryEntityManager();
        $em->persist($user);
        $em->flush();
        $id = $user->getId();
        $this->assertNotNull($id, 'Id should be assigned');
        $this->assertIsInt($id);
        // Assume other stuff happened in-between the above and here
        $em->persist($user);
        $em->flush();
        $this->assertSame($id, $user->getId(), 'Id should not have changed');
    }

    /**
     * @covers ::persist
     * @covers ::flush
     */
    public function testIdNotAssignedWithoutGeneratedValueAnnotation(): void
    {
        $node = new Entities\Node();
        $id = $node->getNodeId();
        $em = new InMemoryEntityManager();
        $em->persist($node);
        $em->flush();
        $this->assertSame($id, $node->getNodeId(), 'Id must not change');
    }
}
