<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

/**
 * Formulário de edital no site.
 *
 * @since  1.0.0
 */
class EditalformController extends CadastroFormController
{
	protected $view_item = 'editalform';

	protected $view_list = 'cadastroeditais';

	protected $context = 'editalform';
}
