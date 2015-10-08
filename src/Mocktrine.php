<?php

namespace Firehed;

use PHPUnit_Framework_MockObject_MockObject as Mock;
use Doctrine\Common\Persistence\Mapping\MappingException;

trait Mocktrine
{

    private $mocked_objects = [];

    public function addDoctrineMock(Mock $mock, array $props = []) {
        $mocked_class = get_parent_class($mock);
        if (!isset($this->mocked_objects[$mocked_class])) {
            $this->mocked_objects[$mocked_class] = [];
        }
        $this->mocked_objects[$mocked_class][] = ['__object' => $mock]+$props;
        return $this;

    }

    public function getMockObjectManager() {
        $mock = $this->getMock('Doctrine\Common\Persistence\ObjectManager');

        foreach ($this->mocked_objects as $mocked_class => $mocks) {
            $repo = $this->getMockRepoForClass($mocked_class);

            $mock->method('getRepository')
                ->with($mocked_class)
                ->will($this->returnValue($repo));
        }

        $mock->method('find')
            ->will($this->returnCallback(function($model, $id) use ($mock) {
                $repo = $mock->getRepository($model);
                if (!$repo) {
                    throw new MappingException(sprintf(
                        "Class '%s' is not mapped in tests",
                        $model));
                }
                return $repo->find($id);
            }));

        return $mock;

    }

    private function getMockRepoForClass($class) {
        $repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();

        $repo->method('find')
            ->will($this->returnCallback(function($id) use ($repo) {
                return $repo->findOneBy(['id' => $id]);
            }));

        $repo->method('findBy')
            ->will($this->returnCallback(function($filters) use ($class) {
                $objects = $this->mocked_objects[$class];
                $filtered = array_filter($objects, function($obj) use ($filters) {
                    foreach ($filters as $key => $value) {
                        if (!isset($obj[$key])) return false;
                    }
                    return $obj[$key] == $value;

                });
                return array_map(function($obj) {
                    return $obj['__object'];
                }, $filtered);
            }));

        $repo->method('findOneBy')
            ->will($this->returnCallback(function($filters) use ($repo) {
                $objs = $repo->findBy($filters);
                if (!$objs) return null;
                return current($objs);
            }));

        return $repo;

    }

}
