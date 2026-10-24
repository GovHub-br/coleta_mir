<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Exception;

use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;
use RuntimeException;
use Throwable;

final class OidcException extends RuntimeException
{
    public function __construct(
        public readonly OidcErrorCode $errorCode,
        string $internalMessage,
        ?Throwable $previous = null,
        public readonly ?OidcFailureCategory $failureCategory = null,
    ) {
        parent::__construct($internalMessage, 0, $previous);
    }
}
