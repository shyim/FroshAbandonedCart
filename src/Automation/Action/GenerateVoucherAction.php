<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Action;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class GenerateVoucherAction implements ActionInterface
{
    private const DEFAULT_CODE_PATTERN = 'RECOVER-%s%s%d%d';

    /**
     * @param EntityRepository<PromotionCollection> $promotionRepository
     * @param EntityRepository<PromotionIndividualCodeCollection> $promotionIndividualCodeRepository
     */
    public function __construct(
        private readonly EntityRepository $promotionRepository,
        private readonly EntityRepository $promotionIndividualCodeRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getType(): string
    {
        return 'generate_voucher';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(AbandonedCartEntity $cart, array $config, ActionContext $context): void
    {
        $promotionId = $config['promotionId'] ?? null;
        $codePattern = $config['codePattern'] ?? self::DEFAULT_CODE_PATTERN;

        if ($promotionId === null) {
            $this->logger->warning('GenerateVoucherAction: No promotion ID configured');

            return;
        }

        $promotion = $this->getPromotion($promotionId, $context->getContext());
        if ($promotion === null) {
            $this->logger->warning('GenerateVoucherAction: Promotion not found', ['promotionId' => $promotionId]);

            return;
        }

        if (!$promotion->isUseIndividualCodes()) {
            $this->logger->warning('GenerateVoucherAction: Promotion does not use individual codes', ['promotionId' => $promotionId]);

            return;
        }

        try {
            $code = $this->generateCode($codePattern);

            $this->promotionIndividualCodeRepository->create([
                [
                    'id' => Uuid::randomHex(),
                    'promotionId' => $promotionId,
                    'code' => $code,
                ],
            ], $context->getContext());

            $context->setVoucherCode($code);

            $this->logger->info('GenerateVoucherAction: Generated voucher code', [
                'code' => $code,
                'promotionId' => $promotionId,
                'cartId' => $cart->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('GenerateVoucherAction: Failed to generate voucher code', [
                'error' => $e->getMessage(),
                'promotionId' => $promotionId,
                'cartId' => $cart->getId(),
            ]);
        }
    }

    private function getPromotion(string $id, \Shopware\Core\Framework\Context $context): ?PromotionEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->setLimit(1);

        return $this->promotionRepository->search($criteria, $context)->getEntities()->first();
    }

    private function generateCode(string $pattern): string
    {
        $result = '';
        $patternLength = \strlen($pattern);
        $i = 0;

        while ($i < $patternLength) {
            if ($pattern[$i] === '%' && isset($pattern[$i + 1])) {
                $type = $pattern[$i + 1];
                if ($type === 's') {
                    $result .= \chr(Random::getInteger(65, 90)); // A-Z
                    $i += 2;
                } elseif ($type === 'd') {
                    $result .= (string) Random::getInteger(0, 9); // 0-9
                    $i += 2;
                } else {
                    $result .= $pattern[$i];
                    ++$i;
                }
            } else {
                $result .= $pattern[$i];
                ++$i;
            }
        }

        return $result;
    }
}
