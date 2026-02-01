<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Integration;

use Doctrine\DBAL\Connection;
use Frosh\AbandonedCart\Automation\Action\ActionContext;
use Frosh\AbandonedCart\Automation\Action\SendEmailAction;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Frosh\AbandonedCart\Entity\AbandonedCartLineItemCollection;
use Frosh\AbandonedCart\Entity\AbandonedCartLineItemEntity;
use Frosh\AbandonedCart\Migration\Migration1769932946CreateMailTemplate;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Email;

class MailTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    private ?Email $capturedEmail = null;

    /**
     * @var callable|null
     */
    private $mailListener = null;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->eventDispatcher = static::getContainer()->get(EventDispatcherInterface::class);

        $this->ensureMailTemplateExists();
        $this->capturedEmail = null;
    }

    protected function tearDown(): void
    {
        if ($this->mailListener !== null) {
            $this->eventDispatcher->removeListener(MailBeforeSentEvent::class, $this->mailListener);
            $this->mailListener = null;
        }
    }

    public function testMailTemplateExists(): void
    {
        $mailTemplateTypeId = $this->connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => Migration1769932946CreateMailTemplate::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME]
        );

        static::assertNotFalse($mailTemplateTypeId, 'Mail template type should exist');

        $mailTemplateId = $this->connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId',
            ['typeId' => $mailTemplateTypeId]
        );

        static::assertNotFalse($mailTemplateId, 'Mail template should exist');

        $translations = $this->connection->fetchAllAssociative(
            'SELECT * FROM mail_template_translation WHERE mail_template_id = :id',
            ['id' => $mailTemplateId]
        );

        static::assertNotEmpty($translations, 'Mail template should have translations');
    }

    public function testSendEmailWithMailTemplate(): void
    {
        $mailTemplateId = $this->getMailTemplateId();

        $this->registerMailListener();

        $action = new SendEmailAction(
            static::getContainer()->get('Shopware\Core\Content\Mail\Service\MailService'),
            static::getContainer()->get('mail_template.repository'),
            static::getContainer()->get('logger')
        );

        $cart = $this->createAbandonedCartWithLineItems();
        $context = new ActionContext(Context::createDefaultContext());
        $context->setVoucherCode('TESTVOUCHER10');

        $action->execute($cart, ['mailTemplateId' => $mailTemplateId], $context);

        static::assertNotNull($this->capturedEmail, 'Email should have been sent');

        $htmlBody = $this->capturedEmail->getHtmlBody();
        static::assertIsString($htmlBody);

        static::assertStringContainsString('Max', $htmlBody);
        static::assertStringContainsString('Mustermann', $htmlBody);
        static::assertStringContainsString('Test Product 1', $htmlBody);
        static::assertStringContainsString('Test Product 2', $htmlBody);
        static::assertStringContainsString('TESTVOUCHER10', $htmlBody);
    }

    public function testSendEmailWithoutVoucher(): void
    {
        $mailTemplateId = $this->getMailTemplateId();

        $this->registerMailListener();

        $action = new SendEmailAction(
            static::getContainer()->get('Shopware\Core\Content\Mail\Service\MailService'),
            static::getContainer()->get('mail_template.repository'),
            static::getContainer()->get('logger')
        );

        $cart = $this->createAbandonedCartWithLineItems();
        $context = new ActionContext(Context::createDefaultContext());

        $action->execute($cart, ['mailTemplateId' => $mailTemplateId], $context);

        static::assertNotNull($this->capturedEmail, 'Email should have been sent');

        $htmlBody = $this->capturedEmail->getHtmlBody();
        static::assertIsString($htmlBody);

        static::assertStringContainsString('Test Product 1', $htmlBody);
        static::assertStringNotContainsString('TESTVOUCHER', $htmlBody);
        static::assertStringNotContainsString('Special offer', $htmlBody);
    }

    public function testMailTemplateRendersLineItems(): void
    {
        $mailTemplateId = $this->getMailTemplateId();

        $this->registerMailListener();

        $action = new SendEmailAction(
            static::getContainer()->get('Shopware\Core\Content\Mail\Service\MailService'),
            static::getContainer()->get('mail_template.repository'),
            static::getContainer()->get('logger')
        );

        $cart = $this->createAbandonedCartWithLineItems();
        $context = new ActionContext(Context::createDefaultContext());

        $action->execute($cart, ['mailTemplateId' => $mailTemplateId], $context);

        static::assertNotNull($this->capturedEmail, 'Email should have been sent');

        $htmlBody = $this->capturedEmail->getHtmlBody();
        static::assertIsString($htmlBody);

        static::assertStringContainsString('Test Product 1', $htmlBody);
        static::assertStringContainsString('Test Product 2', $htmlBody);

        $plainBody = $this->capturedEmail->getTextBody();
        static::assertIsString($plainBody);

        static::assertStringContainsString('Test Product 1', $plainBody);
        static::assertStringContainsString('Test Product 2', $plainBody);
    }

    private function registerMailListener(): void
    {
        $this->mailListener = function (MailBeforeSentEvent $event): void {
            $this->capturedEmail = $event->getMessage();
            $event->stopPropagation();
        };

        $this->eventDispatcher->addListener(MailBeforeSentEvent::class, $this->mailListener, 1000);
    }

    private function ensureMailTemplateExists(): void
    {
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM mail_template_type WHERE technical_name = :name',
            ['name' => Migration1769932946CreateMailTemplate::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME]
        );

        if (!$exists) {
            $migration = new Migration1769932946CreateMailTemplate();
            $migration->update($this->connection);
        }
    }

    private function getMailTemplateId(): string
    {
        $mailTemplateTypeId = $this->connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => Migration1769932946CreateMailTemplate::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME]
        );

        static::assertNotFalse($mailTemplateTypeId);

        $mailTemplateId = $this->connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId',
            ['typeId' => $mailTemplateTypeId]
        );

        static::assertNotFalse($mailTemplateId);

        return Uuid::fromBytesToHex($mailTemplateId);
    }

    private function createAbandonedCartWithLineItems(): AbandonedCartEntity
    {
        $cart = new AbandonedCartEntity();
        $cart->setId(Uuid::randomHex());
        $cart->setCustomerId(Uuid::randomHex());
        $cart->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $cart->setTotalPrice(149.98);
        $cart->setCurrencyIsoCode('EUR');

        $customer = new CustomerEntity();
        $customer->setId($cart->getCustomerId());
        $customer->setEmail('max@example.com');
        $customer->setFirstName('Max');
        $customer->setLastName('Mustermann');

        $salutation = new SalutationEntity();
        $salutation->setId(Uuid::randomHex());
        $salutation->setTranslated(['displayName' => 'Mr.']);
        $customer->setSalutation($salutation);

        $cart->setCustomer($customer);

        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $criteria = new Criteria([TestDefaults::SALES_CHANNEL]);
        $criteria->addAssociation('domains');
        $salesChannel = $salesChannelRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        $cart->setSalesChannel($salesChannel);

        $lineItem1 = new AbandonedCartLineItemEntity();
        $lineItem1->setId(Uuid::randomHex());
        $lineItem1->setAbandonedCartId($cart->getId());
        $lineItem1->setLabel('Test Product 1');
        $lineItem1->setQuantity(2);
        $lineItem1->setUnitPrice(49.99);
        $lineItem1->setTotalPrice(99.98);
        $lineItem1->setType('product');
        $lineItem1->setReferencedId(Uuid::randomHex());

        $lineItem2 = new AbandonedCartLineItemEntity();
        $lineItem2->setId(Uuid::randomHex());
        $lineItem2->setAbandonedCartId($cart->getId());
        $lineItem2->setLabel('Test Product 2');
        $lineItem2->setQuantity(1);
        $lineItem2->setUnitPrice(50.00);
        $lineItem2->setTotalPrice(50.00);
        $lineItem2->setType('product');
        $lineItem2->setReferencedId(Uuid::randomHex());

        $cart->setLineItems(new AbandonedCartLineItemCollection([$lineItem1, $lineItem2]));

        return $cart;
    }
}
