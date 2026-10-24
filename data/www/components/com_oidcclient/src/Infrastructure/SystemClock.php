<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Infrastructure;

use DateTimeImmutable;
use JoomlaOidc\Component\OidcClient\Site\Contract\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
