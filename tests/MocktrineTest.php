<?php

namespace Firehed;

/**
 * @coversDefaultClass Firehed\MocktrineTest
 * @covers ::<protected>
 * @covers ::<private>
 */
class MocktrineTest extends \PHPUnit_Framework_TestCase
{

    use Mocktrine;

    /**
     * @covers ::addDoctrineMock
     */
    public function testAddDoctrineMock() {
        $mockObj = $this->getMock('Firehed\Mocktrine\A');
        $this->assertSame($this,
            $this->addDoctrineMock($mockObj),
            'Mocktrine->addDoctrineMock did not return $this');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testGetMockObjectManager() {
        $mockObj = $this->getMock('Firehed\Mocktrine\A');
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertInstanceOf('Doctrine\Common\Persistency\ObjectManager',
            $om,
            'Moctrine->getMockObjectManager() returned wrong type');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testManagerFind() {
        $mockObj = $this->getMock('Firehed\Mocktrine\A');
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertSame($mockObj,
            $om->find('Firehed\Mocktrine\A', 3),
            'Manager->find(class, id) failed');
    }


    /**
     * @covers ::getMockObjectManager
     */
    public function testManagerGetRepository() {
        $mockObj = $this->getMock('Firehed\Mocktrine\A');
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $repo = $om->getRepository('Firehed\Mocktrine\A');
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository',
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
        $mockObj = $this->getMock('Firehed\Mocktrine\A');
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertSame([$mockObj],
            $om->getRepository('Firehed\Mocktrine\A')->findBy(['id' => 3]),
            'repo->findBy([id=>id]) failed');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testRepoFindOneBy() {
        $mockObj = $this->getMock('Firehed\Mocktrine\A');
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertSame($mockObj,
            $om->getRepository('Firehed\Mocktrine\A')->findOneBy(['id' => 3]),
            'repo->findOneBy([id=>id]) failed');
    }

    /**
     * @covers ::getMockObjectManager
     */
    public function testNegativeFilteringOnProperties() {
        $mockObj = $this->getMock('Firehed\Mocktrine\A');
        $this->addDoctrineMock($mockObj, ['id' => 3]);

        $om = $this->getMockObjectManager();
        $this->assertNull($om->find('Firehed\Mocktrine\A', 4),
            'Manager->find(class, id) returned an object it should not have');
    }
 




}

class A {

}
