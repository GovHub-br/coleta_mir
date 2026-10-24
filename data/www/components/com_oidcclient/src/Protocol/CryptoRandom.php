<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;

final class CryptoRandom
{
    private const int RANDOM_BYTES = 32;

    public function generateState(): string
    {
        return $this->base64urlEncode($this->randomBytes());
    }

    public function generateNonce(): string
    {
        return $this->base64urlEncode($this->randomBytes());
    }

    public function generatePkceVerifier(): string
    {
        $bytes = $this->randomBytes();
        $verifier = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        if (\strlen($verifier) < 43 || \strlen($verifier) > 128) {
            throw new OidcException(
                OidcErrorCode::InternalError,
                'PKCE verifier generated with unexpected length.',
            );
        }

        return $verifier;
    }

    public function derivePkceChallengeS256(string $verifier): string
    {
        $hash = hash('sha256', $verifier, true);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    private function randomBytes(): string
    {
        try {
            return random_bytes(self::RANDOM_BYTES);
        } catch (\Exception $e) {
            throw new OidcException(
                OidcErrorCode::InternalError,
                'CSPRNG source failed.',
                $e,
            );
        }
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
