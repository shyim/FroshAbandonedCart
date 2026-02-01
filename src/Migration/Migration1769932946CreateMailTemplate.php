<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1769932946CreateMailTemplate extends MigrationStep
{
    public const MAIL_TEMPLATE_TYPE_TECHNICAL_NAME = 'frosh_abandoned_cart.reminder';

    private const GERMAN_LANGUAGE_NAME = 'Deutsch';
    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1769932946;
    }

    public function update(Connection $connection): void
    {
        $templateTypeId = $this->createMailTemplateType($connection);
        $this->createMailTemplate($templateTypeId, $connection);
    }

    private function createMailTemplateType(Connection $connection): string
    {
        $templateTypeId = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => self::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME]
        );

        if ($templateTypeId) {
            return $templateTypeId;
        }

        $templateTypeId = Uuid::randomBytes();

        $connection->insert('mail_template_type', [
            'id' => $templateTypeId,
            'technical_name' => self::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME,
            'available_entities' => json_encode([
                'customer' => 'customer',
                'abandonedCart' => 'frosh_abandoned_cart',
                'lineItems' => 'frosh_abandoned_cart_line_item',
                'salesChannel' => 'sales_channel',
                'voucherCode' => null,
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $templateTypeId,
                'language_id' => $defaultLanguageId,
                'name' => 'Abandoned Cart Reminder',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($englishLanguageId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $templateTypeId,
                'language_id' => $englishLanguageId,
                'name' => 'Abandoned Cart Reminder',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($germanLanguageId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $templateTypeId,
                'language_id' => $germanLanguageId,
                'name' => 'Warenkorb-Erinnerung',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $templateTypeId;
    }

    private function createMailTemplate(string $templateTypeId, Connection $connection): void
    {
        $templateId = $connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId',
            ['typeId' => $templateTypeId]
        );

        if ($templateId) {
            return;
        }

        $templateId = Uuid::randomBytes();

        $connection->insert('mail_template', [
            'id' => $templateId,
            'mail_template_type_id' => $templateTypeId,
            'system_default' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);

        $enHtml = $this->getMailContent('en-html');
        $enPlain = $this->getMailContent('en-plain');
        $deHtml = $this->getMailContent('de-html');
        $dePlain = $this->getMailContent('de-plain');

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $defaultLanguageId,
                'subject' => 'Your cart is waiting for you at {{ salesChannel.name }}',
                'description' => 'Abandoned Cart Reminder with Voucher',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $enHtml,
                'content_plain' => $enPlain,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($englishLanguageId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $englishLanguageId,
                'subject' => 'Your cart is waiting for you at {{ salesChannel.name }}',
                'description' => 'Abandoned Cart Reminder with Voucher',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $enHtml,
                'content_plain' => $enPlain,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($germanLanguageId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $germanLanguageId,
                'subject' => 'Ihr Warenkorb wartet auf Sie bei {{ salesChannel.name }}',
                'description' => 'Warenkorb-Erinnerung mit Gutschein',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $deHtml,
                'content_plain' => $dePlain,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function fetchLanguageIdByName(string $languageName, Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            'SELECT id FROM `language` WHERE `name` = :languageName',
            ['languageName' => $languageName]
        );

        if ($result === false) {
            return null;
        }

        return (string) $result;
    }

    private function getMailContent(string $name): string
    {
        $path = \dirname(__DIR__) . '/Resources/mails/abandoned_cart_reminder/' . $name . '.html.twig';

        if (!file_exists($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }
}
