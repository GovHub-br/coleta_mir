<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Perguntaform;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Administrator\Helper\EditalHelper;
use Comedital\Component\Edital\Site\View\Cadastro\FormHtmlView;

class HtmlView extends FormHtmlView
{
	public $listView = 'cadastroperguntas';

	public $formController = 'perguntaform';

	public $editalTemAdesoes = false;

	/**
	 * @return  void
	 */
	protected function afterLoad()
	{
		$editalId = (int) ($this->item->co_edital ?? 0);

		if ($editalId)
		{
			$this->editalTemAdesoes = EditalHelper::countRelated('#__tb_adesao', 'co_edital', $editalId) > 0;
		}
	}
}
