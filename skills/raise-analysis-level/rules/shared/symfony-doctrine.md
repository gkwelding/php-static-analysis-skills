---
title: Symfony and Doctrine
tags: symfony, doctrine, phpstan-symfony, phpstan-doctrine, psalm-plugin-symfony, container, repositories, entities
---

## Symfony and Doctrine

Checked against Symfony 8.1, Doctrine ORM 3.7, phpstan-symfony 2.0, phpstan-doctrine 2.0 and psalm/plugin-symfony 5.3. Without their config, the extensions guess, and many "errors" are the analyser lacking information.

### 1. Give the Extensions What They Need

```neon
parameters:
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
    doctrine:
        objectManagerLoader: tests/object-manager.php
```

- `containerXmlPath`: lets PHPStan type `$container->get('...')` and report unknown (`symfonyContainer.serviceNotFound`) or private services. The file exists once the dev container is built (`bin/console cache:clear` in the dev environment). If the config points at it and it's missing, build it; don't remove the setting.
- `objectManagerLoader`: lets phpstan-doctrine validate DQL and infer query results. Without it, `getQuery()->getResult()` is `mixed`; with it, the same call is `list<Product>`.

```php
<?php

// tests/object-manager.php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
```

Adding either is a config change; propose it.

### 2. Repositories

phpstan-doctrine types `$em->getRepository(Product::class)` as the entity's repository class, and `find()` / `findOneBy()` as `Product|null`, `findBy()` as `list<Product>`. It also checks `findBy()` field names (`doctrine.findByArgument`: "entity App\Entity\Product does not have a field named $nam").

Custom repositories need their generic type, as MakerBundle generates it:

```php
/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
```

Results from `find()` are nullable. Handle that the same way as anywhere else (`no-cheating.md` section 4), not with `@var Product`.

### 3. Entity Property vs Column Types

`doctrine.columnType` compares the property type with the mapping:

```
Property App\Entity\Product::$name type mapping mismatch: property can contain string|null but database expects string.
Property App\Entity\Product::$stock type mapping mismatch: database can contain int|null but property expects int.
```

**Incorrect** (column is nullable, property isn't):

```php
#[ORM\Column(nullable: true)]
private int $stock = 0;
```

**Correct:** make them agree in the direction the data really goes: either the column is `NOT NULL` (and a migration is needed) or the property is `?int` and callers handle null.

The first message is MakerBundle's default (`private ?string $name = null` on a non-null column). Fixing it means initialising the property in the constructor. phpstan-doctrine has `allowNullablePropertyForRequiredField: true` for teams that accept that pattern; it's a project-wide policy, so ask rather than enable it to clear errors.

### 4. Final Entities

Psalm's `ClassMustBeFinal` fires on entities too. Doctrine's proxies extend entity classes unless native lazy objects are enabled, and phpstan-doctrine reports `doctrine.finalEntity` / `doctrine.finalConstructor` where `final` would break that. Don't make entities final to satisfy Psalm unless the project uses native lazy objects; if it doesn't, mark them `@api` or exclude the directory from the issue (an existing-config decision for the user).

### 5. Psalm Plugins

```xml
<plugins>
    <pluginClass class="Psalm\SymfonyPsalmPlugin\Plugin">
        <containerXml>var/cache/dev/App_KernelDevDebugContainer.xml</containerXml>
    </pluginClass>
</plugins>
```

With the container it reports `ServiceNotFound` and `ContainerDependency` (the container injected into a service; inject the dependencies instead). Doctrine support for Psalm is `weirdan/doctrine-psalm-plugin`.

### Checklist

- [ ] `containerXmlPath` / `<containerXml>` points at a built container
- [ ] Repositories carry `@extends ServiceEntityRepository<Entity>`
- [ ] `find()` / `findOneBy()` results handled as nullable
- [ ] Property types and column nullability agree
- [ ] Entities not made final without native lazy objects
