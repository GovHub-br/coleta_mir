<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Model;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Administrator\Model\PerguntaModel as AdminPerguntaModel;
use Comedital\Component\Edital\Site\Model\Cadastro\FormModelTrait;

/**
 * Formulário de pergunta no site.
 *
 * @since  1.0.0
 */
class PerguntaformModel extends AdminPerguntaModel
{
	use FormModelTrait;
}
