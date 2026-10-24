<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

final class DisplayController extends BaseController
{
    protected $default_view = 'dashboard';

    /**
     * @param array<string, string> $urlparams
     */
    public function display($cachable = false, $urlparams = []): static
    {
        return parent::display($cachable, $urlparams);
    }
}
