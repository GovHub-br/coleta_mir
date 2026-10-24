<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Cadastroperguntas;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\View\Cadastro\ListHtmlView;

class HtmlView extends ListHtmlView
{
	public $listView = 'cadastroperguntas';

	public $formController = 'perguntaform';
}
