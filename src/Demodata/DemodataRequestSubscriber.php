<?php declare(strict_types=1);

namespace Frosh\AbandonedCart\Demodata;

use Frosh\AbandonedCart\Entity\AbandonedCartDefinition;
use Shopware\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DemodataRequestSubscriber implements EventSubscriberInterface
{
    private const OPTION_NAME = 'abandoned-carts';

    public static function getSubscribedEvents(): array
    {
        return [
            DemodataRequestCreatedEvent::class => 'onDemodataRequest',
        ];
    }

    public function onDemodataRequest(DemodataRequestCreatedEvent $event): void
    {
        $input = $event->getInput();
        $request = $event->getRequest();

        if (!$input->hasOption(self::OPTION_NAME)) {
            return;
        }

        $count = $input->getOption(self::OPTION_NAME);

        if ($count === null) {
            return;
        }

        $request->add(AbandonedCartDefinition::class, (int) $count);
    }
}
