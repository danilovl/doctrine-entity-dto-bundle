[![phpunit](https://github.com/danilovl/doctrine-entity-dto-bundle/actions/workflows/phpunit.yml/badge.svg)](https://github.com/danilovl/doctrine-entity-dto-bundle/actions/workflows/phpunit.yml)
[![downloads](https://img.shields.io/packagist/dt/danilovl/doctrine-entity-dto-bundle)](https://packagist.org/packages/danilovl/doctrine-entity-dto-bundle)
[![latest Stable Version](https://img.shields.io/packagist/v/danilovl/doctrine-entity-dto-bundle)](https://packagist.org/packages/danilovl/doctrine-entity-dto-bundle)
[![license](https://img.shields.io/packagist/l/danilovl/doctrine-entity-dto-bundle)](https://packagist.org/packages/danilovl/doctrine-entity-dto-bundle)

# DoctrineEntityDtoBundle #

## About ##

The Symfony bundle provides a simple mechanism to convert Doctrine entities to DTO objects.

### Requirements

* PHP 8.3 or higher
* Symfony 7.0 or higher
* Doctrine 2

### 1. Installation

Install `danilovl/doctrine-entity-dto-bundle` package by Composer:

``` bash
composer require danilovl/doctrine-entity-dto-bundle
```
Add the `DoctrineEntityDtoBundle` to your application's bundles if it does not add automatically:

```php
<?php
// config/bundles.php

return [
    // ...
    Danilovl\DoctrineEntityDtoBundle\DoctrineEntityDtoBundle::class => ['all' => true]
];
```

### 2. Configuration

After installing the bundle, you can change configuration settings in the `danilovl_doctrine_entity_dto.yaml`.

Default configuration.

```yaml
danilovl_doctrine_entity_dto:
  isEnableEntityDTO: false
  isEnableEntityRuntimeNameDTO: false
  isAsEntityDTO: false
  entityDTO: []
  
  isEnableScalarDTO: false
  isAsScalarDTO: false
  scalarDTO: []
```

### 3. Usage

#### 3.1 Entity DTO

The `DoctrineEntityDtoBundle` automatically creates doctrine hydration for every entity class names if `isEnableEntityDTO` is true.

```yaml
danilovl_doctrine_entity_dto:
  isEnableEntityDTO: true
```

You can add a control attribute `isAsEntityDTO` that only entities with this attribute will create DTO hydration.

```yaml
danilovl_doctrine_entity_dto:
  isEnableEntityDTO: true
  isAsEntityDTO: true
```

```php
#[ORM\Table(name: 'cheque')]
#[AsEntityDTO]
class Cheque
```

You can choose your own array of entities.

```yaml
danilovl_doctrine_entity_dto:
  isEnableEntityDTO: true
  entityDTO:
    - App\Domain\Cheque\Entity\Cheque
```

Or you can combine your list with the attribute control.

```yaml
danilovl_doctrine_entity_dto:
  isEnableEntityDTO: true
  isAsEntityDTO: true
  entityDTO:
    - App\Domain\Cheque\Entity\Cheque
```

You only need to use the alias name of the entity and add the entity class name to the `getResult` method.

Alias name selects all data in the table.

```php
$result = $this->entityManager
    ->getRepository(Cheque::class)
    ->baseQueryBuilder()
    ->select('cheque, city, shop, product')
    ->leftJoin('cheque.shop', 'shop')
    ->leftJoin('shop.city', 'city')
    ->leftJoin('cheque.orderList', 'orderList')
    ->leftJoin('orderList.product', 'product')
    ->setMaxResults(10)
    ->getQuery()
    ->getResult(Cheque::class);
```

The result will be the same as Doctrine's result but without a connection to the unit of work.

```php
array:2 [▼
  0 => App\Domain\Cheque\Entity\Cheque {#1107 ▼
    +price: "105.4"
    +chequeNumber: "0119-201703119-02-9380"
    +shop: App\Domain\Shop\Entity\Shop {#1091 ▶}
    +currency: ? App\Domain\Currency\Entity\Currency
    +orderList: ? Doctrine\Common\Collections\Collection
    +walletTransaction: ? App\Domain\Wallet\Entity\WalletTransaction
    #id: 4
    #date: DateTime @1489878000 {#1083 ▶}
    #createdAt: DateTime @1489878000 {#1081 ▶}
    #updatedAt: DateTime @1489878000 {#1071 ▶}
  }
  1 => App\Domain\Cheque\Entity\Cheque {#1094 ▼
    +price: "311.27"
    +chequeNumber: "0019-20170318-05-9278"
    +shop: App\Domain\Shop\Entity\Shop {#1141 ▶}
    +currency: ? App\Domain\Currency\Entity\Currency
    +orderList: ? Doctrine\Common\Collections\Collection
    +walletTransaction: ? App\Domain\Wallet\Entity\WalletTransaction
    #id: 5
    #date: DateTime @1489791600 {#1142 ▶}
    #createdAt: DateTime @1489791600 {#1139 ▶}
    #updatedAt: DateTime @1489791600 {#1138 ▶}
  }
]
```

If you want the name of the DTO class to be different from the entity class name, use the parameter `isEnableEntityRuntimeNameDTO`.

It creates the name based on the pattern `%sRuntimeDTO`.

Note that this feature utilizes the `eval` function.

```php
array:2 [▼
  0 => ChequeRuntimeDTO {#1107 ▼
    +price: "105.4"
    +chequeNumber: "0119-201703119-02-9380"
    +shop: App\Domain\Shop\Entity\Shop {#1091 ▶}
    +currency: ? App\Domain\Currency\Entity\Currency
    +orderList: ? Doctrine\Common\Collections\Collection
    +walletTransaction: ? App\Domain\Wallet\Entity\WalletTransaction
    #id: 4
    #date: DateTime @1489878000 {#1083 ▶}
    #createdAt: DateTime @1489878000 {#1081 ▶}
    #updatedAt: DateTime @1489878000 {#1071 ▶}
  }
  1 => ChequeRuntimeDTO {#1094 ▼
    +price: "311.27"
    +chequeNumber: "0019-20170318-05-9278"
    +shop: App\Domain\Shop\Entity\Shop {#1141 ▶}
    +currency: ? App\Domain\Currency\Entity\Currency
    +orderList: ? Doctrine\Common\Collections\Collection
    +walletTransaction: ? App\Domain\Wallet\Entity\WalletTransaction
    #id: 5
    #date: DateTime @1489791600 {#1142 ▶}
    #createdAt: DateTime @1489791600 {#1139 ▶}
    #updatedAt: DateTime @1489791600 {#1138 ▶}
  }
]
```

#### 3.2 Scalar DTO

If `isAsScalarDTO` is true, it automatically scans the `src` project directory for every file, trying to find a class with the attribute `AsScalarDTO`.

When you use the `AsScalarDTO` attribute, the results of namespaces are cached for `prod` environment.

```yaml
danilovl_doctrine_entity_dto:
  isEnableScalarDTO: true
  isAsScalarDTO: true
```

You can declare your own list of namespaces for the DTO class in the configuration without using the attribute.

```yaml
danilovl_doctrine_entity_dto:
  isEnableScalarDTO: true
  scalarDTO:
    - App\Domain\Cheque\EntityDTO\ChequeDTO
```

Alternatively, you can combine the attribute and list. The final result will be merged.

```yaml
danilovl_doctrine_entity_dto:
  isEnableScalarDTO: true
  isAsScalarDTO: true
  scalarDTO:
    - App\Domain\Cheque\EntityDTO\ChequeDTO
```

Example of `ChequeDTO`.

```php
<?php declare(strict_types=1);

namespace App\Domain\Cheque\EntityDTO;

use Danilovl\DoctrineEntityDtoBundle\Attribute\AsScalarDTO;

#[AsScalarDTO]
class ChequeDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $chequeNumber
    ) {}
}
```

Before using scalar select, you need to set the class name to the static property `$dtoClass`.

After call `getResult`, `ScalarHydration` set to `null` static `$dtoClass` parameter.

It is a limitation of Doctrine hydration that when you use scalar select, the Doctrine `ResultSetMapping` is empty.

```php

ScalarHydration::$dtoClass = ChequeDTO::class;

$this->entityManager
    ->getRepository(Cheque::class)
    ->baseQueryBuilder()
    ->select('cheque.id, cheque.chequeNumber')
    ->setMaxResults(2)
    ->getQuery()
    ->getResult(ChequeDTO::class);
```

As a result, the select query returns an array of DTO objects.

```php
array:2 [▼
  0 => App\Domain\Cheque\EntityDTO\ChequeDTO {#1065 ▼
    +id: 4136
    +chequeNumber: "165791"
  }
  1 => App\Domain\Cheque\EntityDTO\ChequeDTO {#1066 ▼
    +id: 5838
    +chequeNumber: "539349913"
  }
]
```

#### 4. Other

For example, when you use `knplabs/knp-components` to create a paginator and set a Doctrine query to pagination,
if you want to create DTO objects as a result, simply add the class using the `setHydrationMode` method.

```php
$this->paginator->paginate($query->setHydrationMode(class::class));
```

If use Gedmo Translatable DoctrineExtensions additionally needed `setHint`.

```php
$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslationWalker::class);
$query->setHydrationMode(Product::class);
```

## License

The DoctrineEntityDtoBundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
