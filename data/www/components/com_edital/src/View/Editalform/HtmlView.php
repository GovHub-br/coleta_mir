<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Editalform;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\View\Cadastro\FormHtmlView;

class HtmlView extends FormHtmlView
{
	public $listView = 'cadastroeditais';

	public $formController = 'editalform';
}
