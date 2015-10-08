# Mocktrine

Making testing easier since late 2015.

## What it does

Mocktrine adds a simple API to mock a Doctrine entity manager during your tests.
It is designed to *supplement* best practices like Dependency Inversion, *not* replace them.
It is primarily intended for use with testing controllers, but is not restricted to them.

Basically, it makes [this](http://symfony.com/doc/current/cookbook/testing/database.html#mocking-the-repository-in-a-unit-test) mess go away.

## Usage

First, install as a (dev) dependency via composer:

    composer require --dev firehed/mocktrine

Then simply add the trait to any unit test classes that require the
functionality:

    class MyUnitTest extends \PHPUnit_Framework_TestCase {
        use \Firehed\Mocktrine;
        // ...
    }

Doing so adds two new methods to your unit test classes that make mocking
Doctrine easier

### `function addDoctrineMock(PHPUnit_Framework_MockObject_MockObject $mock, array $props = [])`

This method prepares the mocked entity for use with the mock object manager.

The first parameter is your *already mocked* entity which is a dependency of
the system under test.

The second parameters is an array of search properties, used exactly how
`Doctrine\ORM\EntityRepository->findBy()` uses them. It must contain all of the fields that you want to become searchable, but searches need not use all of them. The `find()` methods are hard-coded to expect `id`. The actual properties of the object *are not examined* during searching.

### `function getMockObjectManager()`

This returns a `Doctrine\Common\Persistence\ObjectManager` already configured to return the mock objects previously prepared.

## Limitations & Known Issues

This project makes no attempt to be 100% compatible, but is intended to easily cover the more common use cases.

The following methods should approximately work as expected:

* ObjectManager::find($class, $id)
* ObjectManager::getRepository($class)
* EntityRepository::find($id)
* EntityRepository::findBy(array $fields)
* EntityRepository::findOneBy(array $fields)

The following *may* be added in the future:

* ObjectManager::persist($entity)
* ObjectManager::remove($entity)
* ObjectManager::detach($entity)
* ObjectManager::flush()

There are currently no plans to support more advanced features:

* ObjectManager::getUnitOfWork()
* EntityRepository advanced searching:
  * Sorting
  * IN()
  * findOneBy*Property* magic
* DQL or SQL


## Example
Below is the (slightly redacted) result from `git diff` of a unit test case before and after adding Mocktrine:

```
diff --git a/tests/CheckoutTest.php b/tests/CheckoutTest.php
index 2472aa6..de19ac8 100644
--- a/tests/CheckoutTest.php
+++ b/tests/CheckoutTest.php
@@ -13,6 +13,7 @@ class CheckoutTest extends \PHPUnit_Framework_TestCase
 {

     use EndpointTestTrait;
+    use \Firehed\Mocktrine;

     protected function getEndpoint() {
         return new Checkout();
@@ -66,16 +67,8 @@ class CheckoutTest extends \PHPUnit_Framework_TestCase
                 ->will($this->returnSelf());
         }

-        $mock_repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
-            ->disableOriginalConstructor()
-            ->getMock();
-        $mock_repo->method('findOneBy')
-            ->will($this->returnValue($mock_bill_recipient));
-
-        $mock_manager = $this->getMock('Doctrine\Common\Persistence\ObjectManager');
-        $mock_manager->method('getRepository')
-            ->with('BillRecipient')
-            ->will($this->returnValue($mock_repo));
+        $this->addDoctrineMock($mock_bill_recipient, ['checkout_id' => $id]);
+        $mock_manager = $this->getMockObjectManager();

         $endpoint = $this->getEndpoint();
         $endpoint->setObjectManager($mock_manager);
``` 
