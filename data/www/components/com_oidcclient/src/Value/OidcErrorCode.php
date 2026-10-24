<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

enum OidcErrorCode: string
{
    case InvalidRequest = 'OIDC_INVALID_REQUEST';
    case InvalidGrant = 'OIDC_INVALID_GRANT';
    case ExpiredGrant = 'OIDC_EXPIRED_GRANT';
    case InvalidState = 'OIDC_INVALID_STATE';
    case ExpiredTransaction = 'OIDC_EXPIRED_TRANSACTION';
    case ProviderError = 'OIDC_PROVIDER_ERROR';
    case TokenExchangeFailed = 'OIDC_TOKEN_EXCHANGE_FAILED';
    case InvalidToken = 'OIDC_INVALID_TOKEN';
    case JwksUnavailable = 'OIDC_JWKS_UNAVAILABLE';
    case IdentityNotLinked = 'OIDC_IDENTITY_NOT_LINKED';
    case IdentityConflict = 'OIDC_IDENTITY_CONFLICT';
    case HttpRequestFailed = 'OIDC_HTTP_REQUEST_FAILED';
    case UserNotAllowed = 'OIDC_USER_NOT_ALLOWED';
    case ConfigurationError = 'OIDC_CONFIGURATION_ERROR';
    case LogoutError = 'OIDC_LOGOUT_ERROR';
    case InternalError = 'OIDC_INTERNAL_ERROR';
}
