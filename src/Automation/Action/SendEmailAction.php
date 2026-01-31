<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Action;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

class SendEmailAction implements ActionInterface
{
    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        private readonly MailService $mailService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getType(): string
    {
        return 'send_email';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(AbandonedCartEntity $cart, array $config, ActionContext $context): void
    {
        $mailTemplateId = $config['mailTemplateId'] ?? null;
        $replyTo = $config['replyTo'] ?? null;

        if ($mailTemplateId === null) {
            $this->logger->warning('SendEmailAction: No mail template ID configured');

            return;
        }

        $mailTemplate = $this->getMailTemplate($mailTemplateId, $context->getContext());
        if ($mailTemplate === null) {
            $this->logger->warning('SendEmailAction: Mail template not found', ['mailTemplateId' => $mailTemplateId]);

            return;
        }

        $customer = $cart->getCustomer();
        if ($customer === null) {
            $this->logger->warning('SendEmailAction: Customer not loaded on abandoned cart', ['cartId' => $cart->getId()]);

            return;
        }

        $salesChannel = $cart->getSalesChannel();

        $recipients = [
            $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName(),
        ];

        $data = new DataBag();
        $data->set('recipients', $recipients);
        $data->set('senderName', $mailTemplate->getTranslation('senderName'));
        $data->set('salesChannelId', $cart->getSalesChannelId());
        $data->set('templateId', $mailTemplate->getId());
        $data->set('customFields', $mailTemplate->getCustomFields());
        $data->set('contentHtml', $mailTemplate->getTranslation('contentHtml'));
        $data->set('contentPlain', $mailTemplate->getTranslation('contentPlain'));
        $data->set('subject', $mailTemplate->getTranslation('subject'));
        $data->set('mediaIds', []);

        if ($replyTo !== null && $replyTo !== '') {
            $data->set('senderMail', $replyTo);
        }

        $templateData = [
            'customer' => $customer,
            'abandonedCart' => $cart,
            'lineItems' => $cart->getLineItems(),
            'voucherCode' => $context->getVoucherCode(),
            'salesChannel' => $salesChannel,
        ];

        try {
            $this->mailService->send($data->all(), $context->getContext(), $templateData);
        } catch (\Exception $e) {
            $this->logger->error('SendEmailAction: Failed to send email', [
                'error' => $e->getMessage(),
                'cartId' => $cart->getId(),
                'customerId' => $cart->getCustomerId(),
            ]);
        }
    }

    private function getMailTemplate(string $id, \Shopware\Core\Framework\Context $context): ?MailTemplateEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('media.media');
        $criteria->setLimit(1);

        return $this->mailTemplateRepository->search($criteria, $context)->getEntities()->first();
    }
}
