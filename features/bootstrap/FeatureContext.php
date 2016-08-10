<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Persistence\ObjectRepository;
use Sylius\Component\Association\Model\AssociationTypeInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Webburza\Sylius\CustomersAlsoBoughtBundle\Command\GenerateCommand;

class FeatureContext implements Context
{
    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var Application
     */
    protected $application;

    /**
     * @var CommandTester
     */
    protected $tester;

    /**
     * @var Command
     */
    protected $command;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var PurgerInterface
     */
    protected $ormPurger;

    /**
     * @var FactoryInterface
     */
    protected $associationTypeFactory;

    /**
     * @var ObjectRepository
     */
    protected $associationTypeRepository;

    /**
     * @var FactoryInterface
     */
    protected $orderFactory;

    /**
     * @var FactoryInterface
     */
    protected $productFactory;

    /**
     * @var ObjectRepository
     */
    protected $orderRepository;

    /**
     * @var ObjectRepository
     */
    protected $productRepository;

    /**
     * @var ObjectRepository
     */
    protected $customerRepository;

    /**
     * @var FactoryInterface
     */
    protected $customerFactory;

    /**
     * @var FactoryInterface
     */
    protected $orderItemFactory;

    /**
     * @var int
     */
    protected $associationLimit;

    /**
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;

        $this->entityManager = $kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $this->ormPurger = $kernel->getContainer()->get('sylius.purger.orm_purger');
        $this->associationTypeFactory = $kernel->getContainer()->get('sylius.factory.product_association_type');
        $this->associationTypeRepository = $kernel->getContainer()->get('sylius.repository.product_association_type');
        $this->orderFactory = $kernel->getContainer()->get('sylius.factory.order');
        $this->productFactory = $kernel->getContainer()->get('sylius.factory.product');
        $this->orderRepository = $kernel->getContainer()->get('sylius.repository.order');
        $this->productRepository = $kernel->getContainer()->get('sylius.repository.product');
        $this->customerRepository = $kernel->getContainer()->get('sylius.repository.customer');
        $this->customerFactory = $kernel->getContainer()->get('sylius.factory.customer');
        $this->orderItemFactory = $kernel->getContainer()->get('sylius.factory.order_item');
    }

    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function createSchema(BeforeScenarioScope $scope)
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);
    }

    /**
     * @AfterScenario
     * @param AfterScenarioScope $scope
     */
    public function dropSchema(AfterScenarioScope $scope)
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
    }

    /**
     * @Given /^there is a "([^"]*)" association type named "([^"]*)"$/
     *
     * @param $code
     * @param $name
     */
    public function thereIsAssociationType($code, $name)
    {
        $associationType = $this->getAssociationType($code, $name);

        if (!$associationType)
        {
            /** @var AssociationTypeInterface $associationType */
            $associationType = $this->associationTypeFactory->createNew();

            $associationType->setName($name);
            $associationType->setCode($code);

            $this->entityManager->persist($associationType);
            $this->entityManager->flush();
        }
    }

    /**
     * @Given /^there should be "([^"]*)" association type named "([^"]*)"$/
     *
     * @param $code
     * @param $name
     */
    public function thereShouldBeAssociationType($code, $name)
    {
        $associationType = $this->getAssociationType($code, $name);

        \PHPUnit_Framework_Assert::assertNotNull($associationType);
    }

    /**
     * @Given /^there is no "([^"]*)" association type named "([^"]*)"$/
     *
     * @param $code
     * @param $name
     */
    public function thereIsNoAssociationType($code, $name)
    {
        $associationType = $this->getAssociationType($code, $name);

        if ($associationType) {
            $this->entityManager->remove($associationType);
            $this->entityManager->flush();
        }
    }

    /**
     * @Given /^there should not be "([^"]*)" association type named "([^"]*)"$/
     *
     * @param $code
     * @param $name
     */
    public function thereShouldNotBeAssociationType($code, $name)
    {
        $associationType = $this->associationTypeRepository->findOneBy([
            'name' => $name,
            'code' => $code
        ]);

        \PHPUnit_Framework_Assert::assertNull($associationType);
    }

    /**
     * @Given I run Webburza Sylius Customers Also Bought Generate command
     */
    public function iRunWebburzaSyliusCustomersAlsoBoughtGenerateCommand()
    {
        $commandName = 'webburza:sylius-customers-also-bought:generate';

        $this->application = new Application($this->kernel);
        $this->application->add(new GenerateCommand());
        $this->command = $this->application->find($commandName);
        $this->tester = new CommandTester($this->command);

        $commandParams = [
            'command' => $commandName,
        ];

        if ($this->associationLimit !== null) {
            $commandParams['--limit'] = $this->associationLimit;
        }

        $this->tester->execute($commandParams);
    }

    /**
     * @Then the command should finish successfully
     */
    public function commandSuccess()
    {
        \PHPUnit_Framework_Assert::assertEquals($this->tester->getStatusCode(), 0);
    }

    /**
     * @Then I should see output :text
     *
     * @param $text
     */
    public function iShouldSeeOutput($text)
    {
        \PHPUnit_Framework_Assert::assertContains($text, $this->tester->getDisplay());
    }

    /**
     * @Then I should not see output :text
     *
     * @param $text
     */
    public function iShouldNotSeeOutput($text)
    {
        \PHPUnit_Framework_Assert::assertNotContains($text, $this->tester->getDisplay());
    }

    /**
     * @Given /^there are following orders:$/
     * @Given /^the following orders exist:$/
     * @Given /^there are orders:$/
     * @Given /^the following orders were placed:$/
     *
     * @param TableNode $table
     */
    public function thereAreOrders(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            // Get or create the customer
            $customer = $this->getOrCreateCustomer($row['customer']);

            /** @var OrderInterface $order */
            $order = $this->orderFactory->createNew();

            $order->setNumber($row['number']);
            $order->setCustomer($customer);
            $order->setState($row['state']);
            $order->setCurrency(isset($row['currency']) ? $row['currency'] : '');

            $this->entityManager->persist($order);
        }

        $this->entityManager->flush();
    }

    /**
     * @Given /^there are following products:$/
     * @Given /^the following products exist:$/
     * @Given /^there are products:$/
     *
     * @param TableNode $table
     */
    public function thereAreProducts(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            /** @var ProductInterface $product */
            $product = $this->productFactory->createNew();

            $product->setName($row['name']);
            $product->setPrice(isset($row['price']) ? (int)$row['price'] : 1);

            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();
    }

    /**
     * @Given /^order "([^"]*)" has the following products:$/
     *
     * @param $orderNumber
     * @param TableNode $table
     */
    public function orderHasTheFollowingProducts($orderNumber, TableNode $table)
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneBy([
            'number' => $orderNumber
        ]);

        foreach ($table->getHash() as $row) {
            $product = $this->productRepository->findOneByName($row['name']);

            /** @var OrderItemInterface $orderItem */
            $orderItem = $this->orderItemFactory->createNew();
            $orderItem->setVariant($product->getMasterVariant());

            $order->addItem($orderItem);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @Given /^product "([^"]*)" should only be associated with:$/
     * @param $productName
     * @param TableNode $table
     */
    public function productShouldOnlyBeAssociatedWith($productName, TableNode $table)
    {
        $product = $this->productRepository->findOneByName($productName);
        $associatedProducts = $this->getAssociatedProducts($product);

        $productNames = array_column($table->getHash(), 'name');

        // Assert that the number of associated products is correct
        \PHPUnit_Framework_Assert::assertEquals(count($productNames), count($associatedProducts));

        // Check each association
        foreach ($associatedProducts as $product) {
            \PHPUnit_Framework_Assert::assertContains($product->getName(), $productNames);
        }
    }

    /**
     * @Given /^the following products should have no associations:$/
     * @param TableNode $table
     */
    public function theFollowingProductsShouldHaveNoAssociations(TableNode $table)
    {
        $productNames = array_column($table->getHash(), 'name');

        foreach ($productNames as $productName) {
            $product = $this->productRepository->findOneByName($productName);
            $associatedProducts = $this->getAssociatedProducts($product);

            \PHPUnit_Framework_Assert::assertEmpty($associatedProducts);
        }
    }

    /**
     * @Given /^association limit per product is (\d+)$/
     */
    public function associationLimitPerProductIs($limit)
    {
        $this->associationLimit = $limit;
    }

    /**
     * @param $code
     * @param $name
     *
     * @return AssociationTypeInterface
     */
    protected function getAssociationType($code, $name)
    {
        $repository = $this->associationTypeRepository;

        $associationType = $repository->findOneBy([
            'name' => $name,
            'code' => $code
        ]);

        return $associationType;
    }

    /**
     * @param $email
     * @param bool $persist
     *
     * @return CustomerInterface
     */
    protected function getOrCreateCustomer($email, $persist = true)
    {
        $customer = $this->customerRepository->findOneBy([
            'email' => $email
        ]);

        if (!$customer) {
            /** @var CustomerInterface $customer */
            $customer = $this->customerFactory->createNew();
            $customer->setEmail($email);

            if ($persist) {
                $this->entityManager->persist($customer);
                $this->entityManager->flush($customer);
            }
        }

        return $customer;
    }

    /**
     * @param ProductInterface $product
     *
     * @return ProductInterface[]
     */
    protected function getAssociatedProducts(ProductInterface $product)
    {
        $associatedProducts = [];

        foreach ($product->getAssociations() as $association) {
            if ($association->getType()->getCode() == 'webburza_customers_also_bought_bundle') {
                foreach ($association->getAssociatedObjects() as $object) {
                    $associatedProducts[] = $object;
                }
            }
        }

        return $associatedProducts;
    }
}
