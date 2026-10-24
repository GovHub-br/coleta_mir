<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Contract;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
