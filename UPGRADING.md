# Upgrading

## 3.x

The library now supports only `doctrine/orm` major version 3.
Going forward, this library shares a major version with the `doctrine/orm` version it supports.

There is no 1.x or 2.x line of this package.

### Breaking Changes

Dropped support for ORM v2.

The default mapping driver has changed from annotations to attributes.
Installations that explicitly provided a mapping driver (recommended) are not impacted.

Transitive breakages from ORM v2 -> v3:

Removed `EntityManagerInterface` methods:

- `merge()` — use `persist()` instead
- `transactional()` — renamed to `wrapInTransaction()`
- `createNamedQuery()`
- `createNamedNativeQuery()`
- `getPartialReference()`
- `copy()`
- `getHydrator()`

Signature Changes

- `clear()`: No loger accepts `$objectName`
- `refresh()`: New optional `$lockMode` parameter (currently not used by library)
- `matching()`: Returns `AbstractLazyCollection&Selectable` instead of `Selection`

## 2.x

This version does not exist.

## 1.x

This version does not exist.

## 0.x

This version of Mocktrine is compatible with `doctrine/orm` version 2.
