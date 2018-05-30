<?php

namespace Firehed;

use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityRepository;

/**
 * @coversDefaultClass Firehed\MocktrineTest
 * @covers ::<protected>
 * @covers ::<private>
 */
class MocktrineTest extends \PHPUnit\Framework\TestCase
{

    use Mocktrine;

    /**
     * @covers ::addDoctrineMock
     */
    public function testAddDoctrineMock() {
        $mockObj = $this->createMock(A::class);
        $this->assertSame($this,
            $this->addDoctrineMock($mockObj),
            'Mocktrine->addDoctrineMock did not return $this');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testGetMockObjectManager() {
        $mockObj = $this->createMock(A::class);
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertInstanceOf(ObjectManager::class,
            $om,
            'Moctrine->getMockObjectManager() returned wrong type');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testManagerFind() {
        $mockObj = $this->createMock(A::class);
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertSame($mockObj,
            $om->find(A::class, 3),
            'Manager->find(class, id) failed');
    }


    /**
     * @covers ::getMockObjectManager
     */
    public function testManagerGetRepository() {
        $mockObj = $this->createMock(A::class);
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $repo = $om->getRepository(A::class);
        $this->assertInstanceOf(EntityRepository::class,
            $repo,
            'getRepository returned the wrong type');

        $this->assertSame($mockObj,
            $repo->find(3),
            'repo->find(id) failed');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testRepoFindBy() {
        $mockObj = $this->createMock(A::class);
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertSame([$mockObj],
            $om->getRepository(A::class)->findBy(['id' => 3]),
            'repo->findBy([id=>id]) failed');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testRepoFindOneBy() {
        $mockObj = $this->createMock(A::class);
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertSame($mockObj,
            $om->getRepository(A::class)->findOneBy(['id' => 3]),
            'repo->findOneBy([id=>id]) failed');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testNegativeFilteringOnProperties() {
        $mockObj = $this->createMock(A::class);
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertNull($om->find(A::class, 4),
            'Manager->find(class, id) returned an object it should not have');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testExceptionConsistencyForUnmappedClasses() {
        $om = $this->getMockObjectManager();
        $this->expectException(MappingException::class);
        $om->find('Firehed\B', 1);
    }

}

class A {

}
