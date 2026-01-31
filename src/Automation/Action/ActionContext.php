<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Action;

class ActionContext
{
    private ?string $voucherCode = null;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function getVoucherCode(): ?string
    {
        return $this->voucherCode;
    }

    public function setVoucherCode(?string $voucherCode): void
    {
        $this->voucherCode = $voucherCode;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}
