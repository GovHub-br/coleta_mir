<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\EspelhoDwHelper;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Sincronização do espelho do data warehouse, chamada em segundo plano pelas telas do
 * componente (EspelhoDwHelper::agendarSincronizacao).
 *
 * @since  1.0.9
 */
class EspelhodwController extends BaseController
{
	public function sincronizar()
	{
		$this->checkToken('get');

		if ($this->app->getIdentity()->guest)
		{
			echo json_encode(['success' => false]);
			$this->app->close();
		}

		$resultado = EspelhoDwHelper::precisaSincronizar() ? EspelhoDwHelper::sincronizar() : null;

		echo json_encode([
			'success'      => true,
			'sincronizado' => $resultado !== null,
			'situacao'     => $resultado->st_sincronizacao ?? null,
		]);
		$this->app->close();
	}
}
