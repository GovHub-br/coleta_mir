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

use Joomla\CMS\Form\Form;

/**
 * Reutiliza o filter XML da administração.
 *
 * A classe que usa este trait deve definir $adminFilterForm (nome do XML em
 * administrator/components/com_edital/forms, sem .xml).
 *
 * @since  1.0.0
 */
trait ListModelTrait
{
	/**
	 * @param   array    $data
	 * @param   boolean  $loadData
	 *
	 * @return  Form|null
	 */
	public function getFilterForm($data = [], $loadData = true)
	{
		Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_edital/forms');
		$this->filterFormName = $this->adminFilterForm;

		$form = parent::getFilterForm($data, $loadData);

		if ($form instanceof Form)
		{
			$this->disableInlineFilterHandlers($form);
		}

		return $form;
	}

	/**
	 * Remove onchange/onclick dos filtros (CSP no site) e usa a classe do searchtools.
	 *
	 * @param   Form  $form
	 *
	 * @return  void
	 */
	protected function disableInlineFilterHandlers(Form $form)
	{
		$xml = $form->getXml();

		if (!$xml)
		{
			return;
		}

		foreach ($xml->xpath('//field[@onchange or @onclick]') as $node)
		{
			$hadOnchange = isset($node['onchange']);
			unset($node['onchange'], $node['onclick']);

			if (!$hadOnchange)
			{
				continue;
			}

			$class = (string) ($node['class'] ?? '');

			if (strpos($class, 'js-select-submit-on-change') === false)
			{
				$node['class'] = trim($class . ' js-select-submit-on-change');
			}
		}
	}
}
