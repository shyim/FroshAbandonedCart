<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Action;

use Frosh\AbandonedCart\Automation\Action\ActionContext;
use Frosh\AbandonedCart\Automation\Action\GenerateVoucherAction;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(GenerateVoucherAction::class)]
class GenerateVoucherActionTest extends TestCase
{
    private EntityRepository&MockObject $promotionRepository;

    private EntityRepository&MockObject $promotionIndividualCodeRepository;

    private LoggerInterface&MockObject $logger;

    private GenerateVoucherAction $action;

    protected function setUp(): void
    {
        $this->promotionRepository = $this->createMock(EntityRepository::class);
        $this->promotionIndividualCodeRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new GenerateVoucherAction(
            $this->promotionRepository,
            $this->promotionIndividualCodeRepository,
            $this->logger
        );
    }

    public function testGetType(): void
    {
        static::assertSame('generate_voucher', $this->action->getType());
    }

    public function testExecuteWithMissingPromotionId(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('GenerateVoucherAction: No promotion ID configured');

        $this->promotionRepository->expects(static::never())
            ->method('search');

        $this->promotionIndividualCodeRepository->expects(static::never())
            ->method('create');

        $this->action->execute($cart, [], $context);

        static::assertNull($context->getVoucherCode());
    }

    public function testExecuteWithNullPromotionId(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('GenerateVoucherAction: No promotion ID configured');

        $this->action->execute($cart, ['promotionId' => null], $context);

        static::assertNull($context->getVoucherCode());
    }

    public function testExecuteWithPromotionNotFound(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $promotionId = Uuid::randomHex();

        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new PromotionCollection();
        $searchResult->method('getEntities')->willReturn($collection);

        $this->promotionRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('GenerateVoucherAction: Promotion not found', ['promotionId' => $promotionId]);

        $this->promotionIndividualCodeRepository->expects(static::never())
            ->method('create');

        $this->action->execute($cart, ['promotionId' => $promotionId], $context);

        static::assertNull($context->getVoucherCode());
    }

    public function testExecuteWithPromotionNotUsingIndividualCodes(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $promotionId = Uuid::randomHex();

        $promotion = $this->createPromotion($promotionId, useIndividualCodes: false);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new PromotionCollection([$promotion]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->promotionRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('GenerateVoucherAction: Promotion does not use individual codes', ['promotionId' => $promotionId]);

        $this->promotionIndividualCodeRepository->expects(static::never())
            ->method('create');

        $this->action->execute($cart, ['promotionId' => $promotionId], $context);

        static::assertNull($context->getVoucherCode());
    }

    public function testExecuteSuccessfully(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $promotionId = Uuid::randomHex();

        $promotion = $this->createPromotion($promotionId, useIndividualCodes: true);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new PromotionCollection([$promotion]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->promotionRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->promotionIndividualCodeRepository->expects(static::once())
            ->method('create')
            ->with(
                static::callback(function (array $data) use ($promotionId): bool {
                    static::assertCount(1, $data);
                    static::assertArrayHasKey('id', $data[0]);
                    static::assertArrayHasKey('promotionId', $data[0]);
                    static::assertArrayHasKey('code', $data[0]);
                    static::assertSame($promotionId, $data[0]['promotionId']);
                    static::assertNotEmpty($data[0]['code']);

                    return true;
                }),
                static::anything()
            );

        $this->logger->expects(static::once())
            ->method('info')
            ->with(
                'GenerateVoucherAction: Generated voucher code',
                static::callback(function (array $logContext) use ($promotionId, $cart): bool {
                    static::assertArrayHasKey('code', $logContext);
                    static::assertSame($promotionId, $logContext['promotionId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->action->execute($cart, ['promotionId' => $promotionId], $context);

        static::assertNotNull($context->getVoucherCode());
        static::assertStringStartsWith('RECOVER-', $context->getVoucherCode());
    }

    public function testExecuteWithCustomCodePattern(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $promotionId = Uuid::randomHex();

        $promotion = $this->createPromotion($promotionId, useIndividualCodes: true);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new PromotionCollection([$promotion]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->promotionRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->promotionIndividualCodeRepository->expects(static::once())
            ->method('create')
            ->with(
                static::callback(function (array $data): bool {
                    $code = $data[0]['code'];
                    static::assertStringStartsWith('CUSTOM-', $code);
                    static::assertMatchesRegularExpression('/^CUSTOM-[A-Z][A-Z][0-9]$/', $code);

                    return true;
                }),
                static::anything()
            );

        $this->action->execute($cart, [
            'promotionId' => $promotionId,
            'codePattern' => 'CUSTOM-%s%s%d',
        ], $context);

        static::assertNotNull($context->getVoucherCode());
    }

    public function testExecuteWithLiteralCodePattern(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $promotionId = Uuid::randomHex();

        $promotion = $this->createPromotion($promotionId, useIndividualCodes: true);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new PromotionCollection([$promotion]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->promotionRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->promotionIndividualCodeRepository->expects(static::once())
            ->method('create')
            ->with(
                static::callback(function (array $data): bool {
                    $code = $data[0]['code'];
                    // Pattern with unknown placeholder type (like %x) should be treated as literal
                    static::assertStringContainsString('%x', $code);

                    return true;
                }),
                static::anything()
            );

        $this->action->execute($cart, [
            'promotionId' => $promotionId,
            'codePattern' => 'TEST-%x-CODE',
        ], $context);
    }

    public function testExecuteLogsErrorOnCreateFailure(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $promotionId = Uuid::randomHex();

        $promotion = $this->createPromotion($promotionId, useIndividualCodes: true);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new PromotionCollection([$promotion]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->promotionRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $exception = new \Exception('Database error');
        $this->promotionIndividualCodeRepository->expects(static::once())
            ->method('create')
            ->willThrowException($exception);

        $this->logger->expects(static::once())
            ->method('error')
            ->with(
                'GenerateVoucherAction: Failed to generate voucher code',
                static::callback(function (array $logContext) use ($promotionId, $cart): bool {
                    static::assertSame('Database error', $logContext['error']);
                    static::assertSame($promotionId, $logContext['promotionId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->action->execute($cart, ['promotionId' => $promotionId], $context);

        static::assertNull($context->getVoucherCode());
    }

    private function createAbandonedCart(): AbandonedCartEntity
    {
        $cart = new AbandonedCartEntity();
        $cart->setId(Uuid::randomHex());
        $cart->setCustomerId(Uuid::randomHex());
        $cart->setSalesChannelId(Uuid::randomHex());
        $cart->setTotalPrice(99.99);
        $cart->setCurrencyIsoCode('EUR');

        return $cart;
    }

    private function createPromotion(string $id, bool $useIndividualCodes): PromotionEntity
    {
        $promotion = new PromotionEntity();
        $promotion->setId($id);
        $promotion->setUseIndividualCodes($useIndividualCodes);

        return $promotion;
    }
}
