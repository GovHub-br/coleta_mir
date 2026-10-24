<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use InvalidArgumentException;

final readonly class AssuranceLevel
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^[a-z][a-z0-9:_\/-]{0,127}$/', $this->value)) {
            throw new InvalidArgumentException('Invalid assurance level.');
        }
    }
}
