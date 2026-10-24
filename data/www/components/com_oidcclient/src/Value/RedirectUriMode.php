<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

enum RedirectUriMode: string
{
    case ComponentRoute = 'component_route';
    case SiteRoot = 'site_root';
}
