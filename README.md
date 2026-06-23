# Mocktrine

An in-memory Doctrine mocking library for testing

[![Latest Stable Version](https://poser.pugx.org/firehed/mocktrine/v)](//packagist.org/packages/firehed/mocktrine)
[![License](https://poser.pugx.org/firehed/mocktrine/license)](//packagist.org/packages/firehed/mocktrine)
[![Test](https://github.com/Firehed/mocktrine/workflows/Test/badge.svg)](https://github.com/Firehed/mocktrine/actions?query=workflow%3ATest)
[![Static analysis](https://github.com/Firehed/mocktrine/workflows/Static%20analysis/badge.svg)](https://github.com/Firehed/mocktrine/actions?query=workflow%3A%22Static+analysis%22)
[![Lint](https://github.com/Firehed/mocktrine/workflows/Lint/badge.svg)](https://github.com/Firehed/mocktrine/actions?query=workflow%3ALint)

Mocktrine lets you write unit and integration tests with your real Doctrine models and a real EntityManagerInterfae, without brittle mocks of EntityManagerInterface methods.
Work with data as if it was already in your database, and things should Just Work.

## Quick Start

In your unit tests that need an Entity Manager, use a `new \Firehed\Mocktrine\InMemoryEntityManager`. Done!

Any object with Doctrine's entity attributes (`#[Entity]`, `#[Id]`, `#[Column]`, etc) should work without modification.
Create, update, and retrieve entities without a database connection.
`#[GeneratedValue]` ids will get populated on their initial `flush()`, just like your real database will do.

This library aims to provide as much type information as possible, so that static analysis tools (such as PHPStan) work well without additional plugins.

## Recommended Usage

This library works best when setup in a trait alongside other utility functions:

```php
trait TestTools
{
    private ?EntityManagerInterface $em = null;

    public function getEntityManager(): EntityManagerInterface
    {
        if ($this->em === null) {
            $this->em = new Mocktrine(new AttributeDriver(['path/to/entites']));
        }
        return $this->em;
    }

    private function addPersistedEntity(object $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush(); // To assign PK, if needed
    }

    // Application-specific examples
    public function createUser(): User
    {
        $user = new User();
        $this->addPersistedEntity($user);
        return $user;
    }

    public function createGroup(
        ?User $owner = null,
    ): Group {
        $owner ??= $this->createUser();
        $group = new Group(owner: $owner);
        $this->addPersistedEntity($group);
        return $group;
    }
}
```

```php
class GroupServiceTests extends TestCase
{
    use TestTools;

    public function testCreate(): void
    {
        $service = new GroupService($this->getEntityManager());

        $owner = $this->createUser();
        $group = $service->createGroup(owner: $owner);
        self::assertSame($owner->id, $group->ownerId, 'Owner assignment failed');
    }

    public function testEdit(): void
    {
        $service = new GroupService($this->getEntityManager());
        $group = $this->createGroup();

        $service->updateGroup($group, [
            'name' => 'The Name',
        ]);

        self::assertSame('The Name', $group->name);
    }
}
```
## Supported ORM Features

The following methods on Doctrine's `EntityManagerInterface` should all work as expected:
- find
- persist
- remove
- flush
- getRepository
- getCache (will always return `null`)
- isOpen (will always return `true`)

All methods on the `ObjectRepository` (for various findBy operations) should also work, as well as the non-interface `count($criteria)` method.
`ObjectRepository` also implements the `Selectable` interface (as `EntityRepository` does, which is the returned type from `EntityManager`), so it's also possible to use the `matching(Criteria)` method.

The following methods are **not** supported at this time:
- clear
- detach
- refresh
- getClassMetadata
- getMetadataFactory
- initializeObject
- contains
- getConnection
- getExpressionBuilder
- beginTransaction
- wrapInTransaction
- commit
- rollback
- createQuery
- createNativeQuery
- getReference
- close
- lock
- getEventManager
- getConfiguration
- getUnitOfWork
- newHydrator
- getProxyFactory
- getFilters
- isFiltersStateClean
- hasFilters

## Mapping support

If a MappingDriver is not provided to the `InMemoryEntityManager`, it will default to `AttributeDriver`.
It is STRONGLY RECOMMENDED to always pass the same driver you use in your real application:


```php
$em = new \Firehed\Mocktrine\InMemoryEntityManager(
    new \Doctrine\ORM\Mapping\Driver\AttributeDriver(['src/Model']),
 );
```

You can also grab the value directly from your Doctrine config:
```php
$config = ORMSetup::createAttributeMetadataConfiguration(...);
$driver = $config->getMetadataDriverImpl();
$em = new Firehed\Mocktrine\InMemoryEntityManager($driver);
```

## Why Mocktrine?

### vs SQLite/MySQL/Postgres

1) Speed. Mocktrine tests run entirely in-memory with no database server, connection overhead, or schema setup. This makes them orders of magnitude faster — enabling hundreds or thousands of tests per second.

2) Isolation. Every test gets a clean "database", so you can precisely control workflows and test scenarios. No phantom bugs from a previous run.

Mocktrine excels when testing business logic that involves an EntityManager: services, handlers, API internals, repositories, and more.

### vs mocking `EntityManagerInterface`

1) Avoid boilerplate. Mocktrine was created out of internal tools that existed to make mocking less painful across thousands of tests.

2) Flexibility. Mocks, especially for EntityManager, are often brittle - specific find/findBy/getRepository/... paths that can break on refactors or mask incorrect logic. Mocktrine resolves everything and checks real data.

### Differences from a real database and ORM connection

- Not all ORM methods are supported (see above)
- You _may_ get subtle differences with case-sensitivity and sorting (this depends on the real database and collation settings)
- The EM doesn't close on error (mostly because there are no network errors to be had)

Mocktrine _complements_ real-database integration and end-to-end tests, not replaces them. Use it for the common case of testing business logic, and supplement it with real-database tests for persistence, custom queries, advanced reporting, and more.
