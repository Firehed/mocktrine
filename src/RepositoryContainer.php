<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

/**
 * @internal
 */
class RepositoryContainer
{
    /**
     * Broken generic ~ see https://github.com/phpstan/phpstan/issues/2761
     * @var array<class-string, InMemoryRepository>
     */
    private $values = [];

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return InMemoryRepository<T>
     */
    public function get(string $className): object
    {
        return $this->values[$className];
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     */
    public function has(string $className): bool
    {
        return array_key_exists($className, $this->values);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param InMemoryRepository<T> $repo
     */
    public function set(string $className, InMemoryRepository $repo): void
    {
        $this->values[$className] = $repo;
    }
}
