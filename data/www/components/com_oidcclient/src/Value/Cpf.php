<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

final readonly class Cpf
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^\d{11}$/', $this->value) || preg_match('/^(\d)\1{10}$/', $this->value)) {
            throw new \InvalidArgumentException('CPF must contain 11 valid digits.');
        }

        if (!$this->hasValidCheckDigit(9) || !$this->hasValidCheckDigit(10)) {
            throw new \InvalidArgumentException('CPF check digits are invalid.');
        }
    }

    private function hasValidCheckDigit(int $position): bool
    {
        $sum = 0;

        for ($index = 0; $index < $position; $index++) {
            $sum += (int) $this->value[$index] * ($position + 1 - $index);
        }

        $remainder = $sum % 11;
        $expected = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $this->value[$position] === $expected;
    }
}
