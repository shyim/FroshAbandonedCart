<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Action;

use Frosh\AbandonedCart\Automation\Action\ActionContext;
use Frosh\AbandonedCart\Automation\Action\SendEmailAction;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Frosh\AbandonedCart\Entity\AbandonedCartLineItemCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(SendEmailAction::class)]
class SendEmailActionTest extends TestCase
{
    private MailService&MockObject $mailService;

    private EntityRepository&MockObject $mailTemplateRepository;

    private LoggerInterface&MockObject $logger;

    private SendEmailAction $action;

    protected function setUp(): void
    {
        $this->mailService = $this->createMock(MailService::class);
        $this->mailTemplateRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new SendEmailAction(
            $this->mailService,
            $this->mailTemplateRepository,
            $this->logger
        );
    }

    public function testGetType(): void
    {
        static::assertSame('send_email', $this->action->getType());
    }

    public function testExecuteWithMissingMailTemplateId(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('SendEmailAction: No mail template ID configured');

        $this->mailService->expects(static::never())
            ->method('send');

        $this->action->execute($cart, [], $context);
    }

    public function testExecuteWithNullMailTemplateId(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('SendEmailAction: No mail template ID configured');

        $this->mailService->expects(static::never())
            ->method('send');

        $this->action->execute($cart, ['mailTemplateId' => null], $context);
    }

    public function testExecuteWithMailTemplateNotFound(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $mailTemplateId = Uuid::randomHex();

        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new MailTemplateCollection();
        $searchResult->method('getEntities')->willReturn($collection);

        $this->mailTemplateRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('SendEmailAction: Mail template not found', ['mailTemplateId' => $mailTemplateId]);

        $this->mailService->expects(static::never())
            ->method('send');

        $this->action->execute($cart, ['mailTemplateId' => $mailTemplateId], $context);
    }

    public function testExecuteWithMissingCustomer(): void
    {
        $cart = $this->createAbandonedCart(withCustomer: false);
        $context = new ActionContext(Context::createDefaultContext());
        $mailTemplateId = Uuid::randomHex();

        $mailTemplate = $this->createMailTemplate($mailTemplateId);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new MailTemplateCollection([$mailTemplate]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->mailTemplateRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('SendEmailAction: Customer not loaded on abandoned cart', ['cartId' => $cart->getId()]);

        $this->mailService->expects(static::never())
            ->method('send');

        $this->action->execute($cart, ['mailTemplateId' => $mailTemplateId], $context);
    }

    public function testExecuteSuccessfully(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $context->setVoucherCode('VOUCHER123');
        $mailTemplateId = Uuid::randomHex();

        $mailTemplate = $this->createMailTemplate($mailTemplateId);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new MailTemplateCollection([$mailTemplate]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->mailTemplateRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mailService->expects(static::once())
            ->method('send')
            ->with(
                static::callback(function (array $data) use ($mailTemplateId): bool {
                    static::assertSame(['max@example.com' => 'Max Mustermann'], $data['recipients']);
                    static::assertSame($mailTemplateId, $data['templateId']);
                    static::assertSame('Test Subject', $data['subject']);
                    static::assertSame('<p>Test HTML</p>', $data['contentHtml']);
                    static::assertSame('Test Plain', $data['contentPlain']);

                    return true;
                }),
                static::anything(),
                static::callback(function (array $templateData): bool {
                    static::assertArrayHasKey('customer', $templateData);
                    static::assertArrayHasKey('abandonedCart', $templateData);
                    static::assertArrayHasKey('lineItems', $templateData);
                    static::assertSame('VOUCHER123', $templateData['voucherCode']);

                    return true;
                })
            );

        $this->action->execute($cart, ['mailTemplateId' => $mailTemplateId], $context);
    }

    public function testExecuteWithReplyTo(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $mailTemplateId = Uuid::randomHex();

        $mailTemplate = $this->createMailTemplate($mailTemplateId);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new MailTemplateCollection([$mailTemplate]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->mailTemplateRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mailService->expects(static::once())
            ->method('send')
            ->with(
                static::callback(function (array $data): bool {
                    static::assertSame('reply@example.com', $data['senderMail']);

                    return true;
                }),
                static::anything(),
                static::anything()
            );

        $this->action->execute($cart, [
            'mailTemplateId' => $mailTemplateId,
            'replyTo' => 'reply@example.com',
        ], $context);
    }

    public function testExecuteWithEmptyReplyTo(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $mailTemplateId = Uuid::randomHex();

        $mailTemplate = $this->createMailTemplate($mailTemplateId);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new MailTemplateCollection([$mailTemplate]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->mailTemplateRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mailService->expects(static::once())
            ->method('send')
            ->with(
                static::callback(function (array $data): bool {
                    static::assertArrayNotHasKey('senderMail', $data);

                    return true;
                }),
                static::anything(),
                static::anything()
            );

        $this->action->execute($cart, [
            'mailTemplateId' => $mailTemplateId,
            'replyTo' => '',
        ], $context);
    }

    public function testExecuteLogsErrorOnMailSendFailure(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext(Context::createDefaultContext());
        $mailTemplateId = Uuid::randomHex();

        $mailTemplate = $this->createMailTemplate($mailTemplateId);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new MailTemplateCollection([$mailTemplate]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->mailTemplateRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $exception = new \Exception('SMTP connection failed');
        $this->mailService->expects(static::once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger->expects(static::once())
            ->method('error')
            ->with(
                'SendEmailAction: Failed to send email',
                static::callback(function (array $context) use ($cart): bool {
                    static::assertSame('SMTP connection failed', $context['error']);
                    static::assertSame($cart->getId(), $context['cartId']);
                    static::assertSame($cart->getCustomerId(), $context['customerId']);

                    return true;
                })
            );

        $this->action->execute($cart, ['mailTemplateId' => $mailTemplateId], $context);
    }

    private function createAbandonedCart(bool $withCustomer = true): AbandonedCartEntity
    {
        $cart = new AbandonedCartEntity();
        $cart->setId(Uuid::randomHex());
        $cart->setCustomerId(Uuid::randomHex());
        $cart->setSalesChannelId(Uuid::randomHex());
        $cart->setTotalPrice(99.99);
        $cart->setCurrencyIsoCode('EUR');
        $cart->setLineItems(new AbandonedCartLineItemCollection());

        if ($withCustomer) {
            $customer = new CustomerEntity();
            $customer->setId($cart->getCustomerId());
            $customer->setEmail('max@example.com');
            $customer->setFirstName('Max');
            $customer->setLastName('Mustermann');
            $cart->setCustomer($customer);

            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId($cart->getSalesChannelId());
            $cart->setSalesChannel($salesChannel);
        }

        return $cart;
    }

    private function createMailTemplate(string $id): MailTemplateEntity
    {
        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setId($id);
        $mailTemplate->setTranslated([
            'senderName' => 'Test Shop',
            'subject' => 'Test Subject',
            'contentHtml' => '<p>Test HTML</p>',
            'contentPlain' => 'Test Plain',
        ]);
        $mailTemplate->setCustomFields([]);

        return $mailTemplate;
    }
}
