# Mocktrine

A Doctrine mocking library for testing

[![Latest Stable Version](https://poser.pugx.org/firehed/mocktrine/v)](//packagist.org/packages/firehed/mocktrine)
[![License](https://poser.pugx.org/firehed/mocktrine/license)](//packagist.org/packages/firehed/mocktrine)
[![Test](https://github.com/Firehed/mocktrine/workflows/Test/badge.svg)](https://github.com/Firehed/mocktrine/actions?query=workflow%3ATest)
[![Static analysis](https://github.com/Firehed/mocktrine/workflows/Static%20analysis/badge.svg)](https://github.com/Firehed/mocktrine/actions?query=workflow%3A%22Static+analysis%22)
[![Lint](https://github.com/Firehed/mocktrine/workflows/Lint/badge.svg)](https://github.com/Firehed/mocktrine/actions?query=workflow%3ALint)

## Usage

In your unit tests that need an Entity Manager, use a `new \Firehed\Mocktrine\InMemoryEntityManager`. Done!

Any object with Doctrine's entity annotations (`@Entity`, `@Id`, `@Column`, etc) should work without modification.

This library aims to provide as much type information as possible, so that static analysis tools (such as PHPStan) work well without additional plugins.

### Mapping support

As of version 0.5, any mapping driver supported by Doctrine can be used with this library.
The `InMemoryEntityManager` accepts the driver as an optional parameter.

```diff
- $em = new Mocktrine\InMemoryEntityManager();
+ $em = new Mocktrine\InMemoryEntityManager(
+     \Doctrine\ORM\Mapping\Driver\AttributeDriver(['src/Model']),
+ );
```

You can also grab the value directly from your Doctrine config:
```php
$config = Setup::createAnnotationMetadataDriverConfiguration(...)
$driver = $config->getMetadataDriverImpl();
$em = new Mocktrine\InMemoryEntityManager($driver)
```

If a driver is not provided, it will default to either `SimpleAnnotationReader` or `AnnotationReader` that's used via `Setup::createAnnotationMetadataConfiguration`.
The former will be preferred, but the class has been removed in `doctrine/annotations:2.0`; if your local dependencies allow that version then the latter will be used.

It is RECOMMENDED to always explicitly provide a driver, as that best matches Doctrine's own setup behavior.
Future versions of this library may make this required.

## Supported features

The following methods on Doctrine's `EntityManagerInterface` should all work as expected:
- find
- persist
- remove
- merge
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
- transactional
- commit
- rollback
- createQuery
- createNamedQuery
- createNativeQuery
- createNamedNativeQuery
- getReference
- getPartialReference
- close
- copy
- lock
- getEventManager
- getConfiguration
- getUnitOfWork
- getHydrator
- newHydrator
- getProxyFactory
- getFilters
- isFiltersStateClean
- hasFilters
