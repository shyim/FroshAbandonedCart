<?php declare(strict_types=1);

namespace Frosh\AbandonedCart\Demodata;

use Doctrine\DBAL\Connection;
use Frosh\AbandonedCart\Entity\AbandonedCartDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class AbandonedCartGenerator implements DemodataGeneratorInterface
{
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly AbandonedCartDefinition $definition,
        private readonly Connection $connection,
        private readonly EntityRepository $productRepository,
    ) {
    }

    public function getDefinition(): string
    {
        return AbandonedCartDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $customerIds = $this->getCustomerIds();
        $salesChannelIds = $this->getSalesChannelIds();
        $products = $this->getProducts($context);

        if (empty($customerIds)) {
            $context->getConsole()->progressFinish();
            $context->getConsole()->writeln('<error>No customers found. Please generate them first.</error>');

            return;
        }

        if (empty($salesChannelIds)) {
            $context->getConsole()->progressFinish();
            $context->getConsole()->writeln('<error>No sales channels found.</error>');

            return;
        }

        if ($products->count() === 0) {
            $context->getConsole()->progressFinish();
            $context->getConsole()->writeln('<error>No products with prices found. Please generate products first.</error>');

            return;
        }

        $faker = $context->getFaker();
        $writeContext = WriteContext::createFromContext($context->getContext());

        $existingCombinations = $this->getExistingCombinations();
        $usedCombinations = $existingCombinations;

        $payload = [];
        $generated = 0;
        $maxAttempts = $numberOfItems * 10;
        $attempts = 0;

        while ($generated < $numberOfItems && $attempts < $maxAttempts) {
            ++$attempts;

            $customerId = $faker->randomElement($customerIds);
            $salesChannelId = $faker->randomElement($salesChannelIds);
            $combinationKey = $customerId . '_' . $salesChannelId;

            if (isset($usedCombinations[$combinationKey])) {
                continue;
            }

            $usedCombinations[$combinationKey] = true;

            $lineItems = $this->generateLineItems($faker, $products, random_int(1, 5));

            if (empty($lineItems)) {
                continue;
            }

            $totalPrice = round(array_sum(array_column($lineItems, 'totalPrice')), 2);
            $createdAt = $faker->dateTimeBetween('-60 days', 'now');

            $payload[] = [
                'id' => Uuid::randomHex(),
                'customerId' => $customerId,
                'salesChannelId' => $salesChannelId,
                'totalPrice' => $totalPrice,
                'currencyIsoCode' => 'EUR',
                'lineItems' => $lineItems,
                'createdAt' => $createdAt,
            ];

            ++$generated;

            if (\count($payload) >= 50) {
                $this->writer->upsert($this->definition, $payload, $writeContext);
                $context->getConsole()->progressAdvance(\count($payload));
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert($this->definition, $payload, $writeContext);
            $context->getConsole()->progressAdvance(\count($payload));
        }

        $context->getConsole()->progressFinish();

        if ($generated < $numberOfItems) {
            $context->getConsole()->writeln(\sprintf(
                '<comment>Only %d abandoned carts generated (requested %d). Not enough unique customer/sales-channel combinations available.</comment>',
                $generated,
                $numberOfItems
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function getCustomerIds(): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM customer LIMIT 500');
    }

    /**
     * @return list<string>
     */
    private function getSalesChannelIds(): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');
    }

    /**
     * @return array<string, true>
     */
    private function getExistingCombinations(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(customer_id)) as customer_id, LOWER(HEX(sales_channel_id)) as sales_channel_id FROM frosh_abandoned_cart'
        );

        $combinations = [];
        foreach ($rows as $row) {
            $combinations[$row['customer_id'] . '_' . $row['sales_channel_id']] = true;
        }

        return $combinations;
    }

    private function getProducts(DemodataContext $context): ProductCollection
    {
        $criteria = new Criteria();
        $criteria->setLimit(500);
        $criteria->addFilter(new EqualsFilter('parentId', null));

        return $this->productRepository->search($criteria, $context->getContext())->getEntities();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function generateLineItems(\Faker\Generator $faker, ProductCollection $products, int $count): array
    {
        $lineItems = [];
        $usedProducts = [];
        $productArray = $products->getElements();

        for ($i = 0; $i < $count; ++$i) {
            /** @var ProductEntity $product */
            $product = $faker->randomElement($productArray);

            if (\in_array($product->getId(), $usedProducts, true)) {
                continue;
            }

            $usedProducts[] = $product->getId();

            $price = $product->getCurrencyPrice(Defaults::CURRENCY);

            if ($price === null) {
                continue;
            }

            $quantity = random_int(1, 3);
            $unitPrice = round($price->getGross(), 2);
            $totalPrice = round($unitPrice * $quantity, 2);

            $lineItems[] = [
                'id' => Uuid::randomHex(),
                'productId' => $product->getId(),
                'productVersionId' => $product->getVersionId(),
                'type' => 'product',
                'referencedId' => $product->getId(),
                'quantity' => $quantity,
                'label' => $product->getTranslation('name') ?? $product->getProductNumber(),
                'unitPrice' => $unitPrice,
                'totalPrice' => $totalPrice,
            ];
        }

        return $lineItems;
    }
}
