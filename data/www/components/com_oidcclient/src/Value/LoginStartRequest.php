<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use InvalidArgumentException;

final readonly class LoginStartRequest
{
    public function __construct(
        public string $returnUrl,
    ) {
        $isRootRelative = str_starts_with($this->returnUrl, '/')
            && !str_starts_with($this->returnUrl, '//');
        $isJoomlaRelative = $this->returnUrl === 'index.php'
            || str_starts_with($this->returnUrl, 'index.php?');

        if (
            (!$isRootRelative && !$isJoomlaRelative)
            || preg_match('/[\x00-\x1F\x7F]/', $this->returnUrl)
        ) {
            throw new InvalidArgumentException(
                'Return URL must be an internal relative Joomla URL.',
            );
        }
    }
}
