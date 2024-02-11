<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\ObjectRepository;

/**
 * @covers Firehed\Mocktrine\InMemoryEntityManager
 */
class InMemoryEntityManagerTest extends \PHPUnit\Framework\TestCase
{
    protected function getEntityManager(): InMemoryEntityManager
    {
        return new InMemoryEntityManager(new AttributeDriver(['.']));
    }

    public function testFindWithNoEntity(): void
    {
        $em = $this->getEntityManager();
        $this->assertNull($em->find(Entities\User::class, 10));
    }

    public function testFindWithPersistedEntity(): void
    {
        $user = new Entities\User('1@example.com', 'last', 10);
        $em = $this->getEntityManager();
        $em->merge($user); // This starts managing the entity

        $this->assertSame($user, $em->find(Entities\User::class, 10));
    }

    public function testGetRepository(): void
    {
        $em = $this->getEntityManager();
        $repo = $em->getRepository(Entities\User::class);
        $this->assertInstanceOf(InMemoryRepository::class, $repo);
        $this->assertInstanceOf(ObjectRepository::class, $repo);
        $this->assertSame(Entities\User::class, $repo->getClassName());
    }

    public function testIdIsNotAssignedBeforeFlush(): void
    {
        $user = new Entities\User('1@example.com', 'last');
        $this->assertNull($user->getId(), 'Precheck: Id should not be assigned yet');
        $em = $this->getEntityManager();
        $em->persist($user);
        $this->assertNull($user->getId(), 'Id should not be assigned yet');
    }

    public function testIdIsAssignedAfterFlush(): void
    {
        $user = new Entities\User('1@example.com', 'last');
        $this->assertNull($user->getId(), 'Precheck: Id should not be assigned yet');
        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
        $this->assertNotNull($user->getId(), 'Id should be assigned');
        $this->assertIsInt($user->getId());
    }

    public function testIdIsNotChangedAfterSecondFlush(): void
    {
        $user = new Entities\User('1@example.com', 'last');
        $this->assertNull($user->getId(), 'Precheck: Id should not be assigned yet');
        $em = $this->getEntityManager();
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

    public function testIdNotAssignedWithoutGeneratedValueAnnotation(): void
    {
        $node = new Entities\Node();
        $id = $node->getNodeId();
        $em = $this->getEntityManager();
        $em->persist($node);
        $em->flush();
        $this->assertSame($id, $node->getNodeId(), 'Id must not change');
    }

    public function testRemoveMakesQueryingEntityInaccessable(): void
    {
        $node = new Entities\Node();
        $em = $this->getEntityManager();
        $em->merge($node);
        $this->assertSame(
            $node,
            $em->find(Entities\Node::class, $node->getNodeId()),
            'Merged node should be found',
        );
        $em->remove($node);
        $this->assertSame(
            $node,
            $em->find(Entities\Node::class, $node->getNodeId()),
            'Removed node should still be found before flush',
        );
        $em->flush();
        $this->assertNull(
            $em->find(Entities\Node::class, $node->getNodeId()),
            'Removed node not should be found after flush',
        );
    }

    public function testAddOnFlushCallback(): void
    {
        $em = $this->getEntityManager();
        $flushed1 = $flushed2 = false;
        $em->addOnFlushCallback(function () use (&$flushed1) {
            $flushed1 = true;
        });
        $em->addOnFlushCallback(function () use (&$flushed2) {
            $flushed2 = true;
        });
        $this->assertFalse($flushed1);
        $this->assertFalse($flushed2);
        $em->flush();
        $this->assertTrue($flushed1, 'First callback did not fire');
        $this->assertTrue($flushed2, 'Second callback did not fire');
    }

    public function testStringIdIsGenerated(): void
    {
        $sid = new Entities\StringId();
        $this->assertNull($sid->getId());
        $em = $this->getEntityManager();
        $em->persist($sid);
        $em->flush();
        $this->assertNotNull($sid->getId());
        $this->assertIsString($sid->getId());
    }

    public function testTypedIdIsGenerated(): void
    {
        $tsid = new Entities\TypedId();
        $em = $this->getEntityManager();
        $em->persist($tsid);
        $em->flush();
        $this->assertIsInt($tsid->getId());
    }

    public function testUnspecifiedIdIsString(): void
    {
        $sid = new Entities\UnspecifiedId();
        $this->assertNull($sid->getId());
        $em = $this->getEntityManager();
        $em->persist($sid);
        $em->flush();
        $this->assertNotNull($sid->getId());
        $this->assertIsString($sid->getId());
    }

    /**
     * This tests primarily serves to check that the generics on the
     * EntityManager implementation are as accurate as possible, particularly
     * when multiple different entity types are used in the same repository
     * instance.
     */
    public function testInteractionWithMultipleEntities(): void
    {
        $user = new Entities\User('1@example.com', 'last', 10);
        $stringId = new Entities\StringId();

        $em = $this->getEntityManager();
        $em->persist($user);
        $em->persist($stringId);
        $em->flush();
        $this->assertNotNull($user->getId());
        $this->assertNotNull($stringId->getId());

        $foundUser = $em->find(Entities\User::class, 10);
        $this->assertSame($user, $foundUser);

        $foundStringId = $em->find(Entities\StringId::class, $stringId->getId());
        $this->assertSame($foundStringId, $stringId);
    }

    public function testGeneratedReadonlyIdWorks(): void
    {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->markTestSkipped('Readonly properties need 8.1+');
        }
        $rgid = new Entities\ReadonlyGeneratedId();
        $em = $this->getEntityManager();
        $em->persist($rgid);
        $em->flush();
        $this->assertIsInt($rgid->id);
    }

    public function testConstructorAssignedReadonlyIdWorks(): void
    {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->markTestSkipped('Readonly properties need 8.1+');
        }
        $rgid = new Entities\ReadonlyConstructorId();
        $em = $this->getEntityManager();
        $em->persist($rgid);
        $em->flush();
        $this->assertIsString($rgid->id);
    }
}
