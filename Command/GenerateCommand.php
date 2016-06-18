<?php

namespace Webburza\Sylius\OrderAssociationBundle\Command;

use Doctrine\ORM\EntityManager;
use Sylius\Component\Association\Model\Association;
use Sylius\Component\Association\Model\AssociationType;
use Sylius\Component\Core\Model\Product;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends ContainerAwareCommand
{
    /**
     * @var OutputInterface $output
     */
    protected $output = null;

    protected function configure()
    {
        $this
            ->setName('webburza:sylius-order-association:generate')
            ->setDescription("Generates product associations based on previous orders.")
            ->setHelp("Usage:  <info>$ app/console webburza:sylius-order-association:generate</info>")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        /** @var EntityManager $manager */
        $manager = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        $this->output->writeln('<info>Checking association types...</info>');
        $associationType = $this->getAssociationType($manager);

        if (! $associationType) {
            $this->output->writeln('<info>Creating association type...</info>');
            $associationType = $this->createAssociationType($manager);
        }

        $this->output->writeln('<info>Calculating product associations...</info>');
        $associations = $this->calculateAssociations($manager);

        $this->output->writeln('<info>Updating product associations with new data...</info>');
        $this->generateAssociations($manager, $associationType, $associations);

        $this->output->writeln('<info>Associations generated.</info>');
    }

    /**
     * Grab the association type we need.
     *
     * @param EntityManager $manager
     *
     * @return AssociationType
     */
    private function getAssociationType($manager)
    {
        $repository = $this->getContainer()->get('sylius.repository.product_association_type');

        // Return association type
        return $repository->findOneBy(['code' => 'webburza_order_association_bundle']);
    }

    /**
     * Create the association type we need and return it.
     *
     * @param EntityManager $manager
     *
     * @return AssociationType
     */
    protected function createAssociationType($manager)
    {
        $factory = $this->getContainer()->get('sylius.factory.product_association_type');

        /** @var AssociationType $associationType */
        $associationType = $factory->createNew();

        $associationType->setCode('webburza_order_association_bundle');
        $associationType->setName('Customers also ordered');

        $manager->persist($associationType);
        $manager->flush();

        return $associationType;
    }

    /**
     * Create all required permission entries.
     *
     * @param EntityManager $manager
     *
     * @return array
     */
    protected function calculateAssociations($manager)
    {
        $queryBuilder = $manager->createQueryBuilder();
        $queryBuilder
            ->select('o.id as order_id, p.id as product_id')
            ->from('Sylius\Component\Core\Model\Order', 'o')
            ->leftJoin('Sylius\Component\Core\Model\OrderItem', 'oi', 'WITH', 'o.id = oi.order')
            ->innerJoin('Sylius\Component\Core\Model\ProductVariant', 'pv', 'WITH', 'oi.variant = pv.id')
            ->innerJoin('Sylius\Component\Core\Model\Product', 'p', 'WITH', 'pv.object = p.id')
            ->where('o.state != \'cart\'');

        $query = $queryBuilder->getQuery();

        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $this->output->writeln('Running query:' . $query->getSQL());
        }

        $orderProductsResult = $query->getResult();

        $products = array();
        $orders = array();
        foreach ($orderProductsResult as $orderProduct) {
            $products[$orderProduct['product_id']][] = $orderProduct['order_id'];
            $orders[$orderProduct['order_id']][] = $orderProduct['product_id'];
        }

        unset($orderProductsResult);

        $productRelations = array();
        foreach ($products as $productId => $productOrders) {
            $relatedProducts = array();
            foreach ($productOrders as $orderId) {
                foreach ($orders[$orderId] as $relatedProductId) {
                    if ($relatedProductId != $productId) {
                        if (!isset($relatedProducts[$relatedProductId])) {
                            $relatedProducts[$relatedProductId] = 1;
                        } else {
                            $relatedProducts[$relatedProductId]++;
                        }
                    }
                }
            }
            arsort($relatedProducts);
            $productRelations[$productId] = $relatedProducts;
        }

        $associationLimit = $this->getContainer()->getParameter('webburza.sylius.order_association_bundle.association_limit');
        if ($associationLimit > 0) {
            foreach ($productRelations as $productId => $relatedProducts) {
                $productRelations[$productId] = array_slice($relatedProducts, 0, $associationLimit, true);
            }
        }

        return $productRelations;
    }

    /**
     * @param EntityManager $manager
     * @param AssociationType $associationType
     * @param array $associations
     */
    protected function generateAssociations($manager, $associationType, $associations)
    {
        /** @var \Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository $associationRepository */
        $associationRepository = $this->getContainer()->get('sylius.repository.product_association');

        /** @var \Sylius\Component\Resource\Factory\Factory $associationFactory */
        $associationFactory = $this->getContainer()->get('sylius.factory.product_association');

        /** @var \Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductRepository $productRepository */
        $productRepository = $this->getContainer()->get('sylius.repository.product');

        /** @var \Sylius\Component\Core\Model\Product[] $products */
        $products = $productRepository->findAll();

        /** @var \Sylius\Component\Core\Model\Product[] $products */
        $productObjects = array();
        foreach ($products as $product) {
            if (!isset($associations[$product->getId()])) {
                $association = $associationRepository->findOneBy(array('owner' => $product->getId(), 'type' => $associationType->getId()));

                if ($association) {
                    if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $this->output->writeln('Removing association ' . $association->getId() . ' of product ' . $product->getId());
                    }
                    $manager->remove($association);
                }
            } else {
                $productObjects[$product->getId()] = $product;
            }
        }
        $manager->flush();

        unset($products);

        foreach ($associations as $productId => $productAssociations) {
            /** @var Association $association */
            $association = $associationRepository->findOneBy(array('owner' => $productId, 'type' => $associationType->getId()));

            $productsToAdd = $productAssociations;

            if ($association) {
                /** @var Product[] $associatedProducts */
                $associatedProducts = $association->getAssociatedObjects();
                foreach ($associatedProducts as $associatedProduct) {
                    if (!isset($associations[$productId][$associatedProduct->getId()])) {
                        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                            $this->output->writeln(
                                'Removing product ' . $associatedProduct->getId() . ' from association ' . $association->getId() . ' of product ' . $productId
                            );
                        }

                        $association->removeAssociatedObject($associatedProduct);
                    } else {
                        unset($productsToAdd[$associatedProduct->getId()]);
                    }
                }
            } else {
                $association = $associationFactory->createNew();
                $association->setType($associationType);
                $association->setOwner($productObjects[$productId]);
            }

            foreach ($productsToAdd as $productToAdd => $weight) {
                if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $this->output->writeln(
                        'Adding product ' . $productToAdd . ' to association ' . $association->getId() . ' of product ' . $productId
                    );
                }

                $association->addAssociatedObject($productObjects[$productToAdd]);
            }

            $manager->persist($association);
        }

        $manager->flush();
    }
}
