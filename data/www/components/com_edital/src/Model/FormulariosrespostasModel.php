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

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Model\ItemModel;
use \Comedital\Component\Edital\Site\Helper\AdesaoHelper;

/**
 * Formulariorespostas model - displays responses to a form submission (adesao).
 *
 * @since  1.0.0
 */
class FormulariosrespostasModel extends AdesaorespostasModel
{
	/**
	 * Method to auto-populate the model state.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 *
	 * @throws Exception
	 */
	protected function populateState()
	{
		$app = Factory::getApplication();

		// Load state from the request
		$id = $app->input->get('id');
		$this->setState('formulariorespostas.id', $id);

		// Load the parameters.
		$params = $app->getParams();
		$this->setState('params', $params);
	}

	/**
	 * Method to get an adesao with its respostas.
	 *
	 * @param   integer $id The id of the adesao to get.
	 *
	 * @return  mixed    Object on success, false on failure.
	 *
	 * @throws Exception
	 */
	public function getItem($id = null, $etapa = 'adesao')
	{
		if ($this->_item === null)
		{
			$this->_item = false;

			if (empty($id))
			{
				$id = $this->getState('formulariorespostas.id');
			}

			if (!$id)
			{
				throw new \Exception(Text::_('COM_EDITAL_ERROR_ADESAO_NOT_FOUND'), 404);
			}

			// Get the adesao data
			$this->_item = $this->getAdesao($id);

			if (!$this->_item)
			{
				throw new \Exception(Text::_('COM_EDITAL_ERROR_ADESAO_NOT_FOUND'), 404);
			}
		}

		return $this->_item;
	}
}
