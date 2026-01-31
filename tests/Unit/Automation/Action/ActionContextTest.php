<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Action;

use Frosh\AbandonedCart\Automation\Action\ActionContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(ActionContext::class)]
class ActionContextTest extends TestCase
{
    public function testVoucherCodeIsNullByDefault(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        static::assertNull($context->getVoucherCode());
    }

    public function testSetAndGetVoucherCode(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        $context->setVoucherCode('VOUCHER123');

        static::assertSame('VOUCHER123', $context->getVoucherCode());
    }

    public function testSetVoucherCodeToNull(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        $context->setVoucherCode('VOUCHER123');
        $context->setVoucherCode(null);

        static::assertNull($context->getVoucherCode());
    }

    public function testGetWithDefaultValue(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        static::assertSame('default', $context->get('non_existent', 'default'));
        static::assertNull($context->get('non_existent'));
    }

    public function testSetAndGet(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        $context->set('key1', 'value1');
        $context->set('key2', 123);
        $context->set('key3', ['nested' => 'array']);

        static::assertSame('value1', $context->get('key1'));
        static::assertSame(123, $context->get('key2'));
        static::assertSame(['nested' => 'array'], $context->get('key3'));
    }

    public function testHas(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        static::assertFalse($context->has('key'));

        $context->set('key', 'value');

        static::assertTrue($context->has('key'));
    }

    public function testHasWithNullValue(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        $context->set('null_key', null);

        static::assertTrue($context->has('null_key'));
    }

    public function testAll(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        static::assertSame([], $context->all());

        $context->set('key1', 'value1');
        $context->set('key2', 'value2');

        static::assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $context->all());
    }

    public function testOverwriteExistingKey(): void
    {
        $context = new ActionContext(Context::createDefaultContext());

        $context->set('key', 'old_value');
        $context->set('key', 'new_value');

        static::assertSame('new_value', $context->get('key'));
    }
}
