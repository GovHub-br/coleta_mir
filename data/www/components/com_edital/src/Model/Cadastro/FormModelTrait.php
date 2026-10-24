<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Model\Cadastro;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;

/**
 * Carrega o XML da administração e aplica regras de state no site.
 *
 * @since  1.0.0
 */
trait FormModelTrait
{
	/**
	 * @param   array    $data
	 * @param   boolean  $loadData
	 *
	 * @return  Form|boolean
	 */
	public function getForm($data = array(), $loadData = true)
	{
		Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_edital/forms');
		Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_edital/src/Field');

		$form = parent::getForm($data, $loadData);

		if (empty($form))
		{
			return false;
		}

		if (!Factory::getApplication()->getIdentity()->authorise('core.edit.state', 'com_edital'))
		{
			$form->setFieldAttribute('state', 'disabled', 'true');
			$form->setFieldAttribute('state', 'filter', 'unset');
		}

		return $form;
	}
}
