# Sylius Order Association Bundle

This bundle adds a command to generate product associations from existing orders to Sylius e-commerce platform. Those
associations can be used to show a "Customers Who Bought This Item Also Bought" section.

---

## Installation

  1. require the bundle with Composer:

  ```bash
  $ composer require webburza/sylius-order-association-bundle
  ```

  2. enable the bundle in `app/AppKernel.php`:

  ```php
  public function registerBundles()
  {
    $bundles = array(
      // ...
      new \Webburza\Sylius\OrderAssociationBundle\WebburzaSyliusOrderAssociationBundle(),
      // ...
    );
  }
  ```

  3. This bundle adds a console command that either generates or updates associations for all products. You should run
  it initially to generate associations and after that periodically to update them. It will create a new association
  type and use it for all generated associations.

  ```bash
  $ app/console webburza:sylius-order-association:generate
  ```

  If the command is behaving unusually or if you want more information on what it's doing, add a verbosity flag (-v)

## Sylius version

  If you're using Sylius 0.17 and you have installed it as it's suggested on the Sylius site, you might need to update
  your sylius/sylius package with composer to get the actual 0.17 version. If you don't have the latest 0.17 version of sylius/sylius
  this bundle's command will silently fail to delete old associations. Cases when associations need to be deleted should
  be weird and unusual, because you don't generally delete orders from your database.

## License

This bundle is available under the [MIT license](LICENSE).

## To-do

- Tests
