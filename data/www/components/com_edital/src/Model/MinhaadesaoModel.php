<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Model;
// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\MVC\Model\ItemModel;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\User\UserFactoryInterface;
use \Comedital\Component\Edital\Site\Helper\EditalHelper;

/**
 * Edital model.
 *
 * @since  1.0.0
 */
class MinhaadesaoModel extends ItemModel
{
	public $_item;

	
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 *
	 * @throws Exception
	 */
	protected function populateState()
	{
		
	}

	/**
	 * Method to get an object.
	 *
	 * @param   integer $id The id of the object to get.
	 *
	 * @return  mixed    Object on success, false on failure.
	 *
	 * @throws Exception
	 */
	public function getItem($id = null)
	{
		$Adesao = new AdesaoModel();
		$item = $Adesao->getItem($id);

		return $item;		
	}

	public function getAdesoesByUserId($userId)
	{
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$query
			->select('a.*, e.tp_edital')
			->from($db->quoteName('#__tb_adesao', 'a'))
			->join('INNER', $db->quoteName('#__tb_edital', 'e') . ' ON ' . $db->quoteName('a.co_edital') . ' = ' . $db->quoteName('e.id'))
			->where($db->quoteName('a.state') . ' = 1')
			->where($db->quoteName('e.tp_edital') . ' = ' . $db->quote('adesao'))
			->where($db->quoteName('a.created_by') . ' = ' . (int) $userId);

		$db->setQuery($query);
		return $db->loadObjectList();
	}

	public function getAdesoesByUserIdEdital($userId, $idEdital)
	{
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$query
			->select('*')
			->from($db->quoteName('#__tb_adesao'))
			->where($db->quoteName('created_by') . ' = ' . (int) $userId)
			->where($db->quoteName('co_edital') . ' = ' . (int) $idEdital)
			->where($db->quoteName('state') . ' = 1')
			;

		$db->setQuery($query);
		return $db->loadObjectList();
	}

	public function getMarcoTemporal($adesaoId, $dsMarco){
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$query
			->select('*')
			->from($db->quoteName('#__tb_marco_temporal'))
			->where($db->quoteName('co_adesao') . ' = ' . (int) $adesaoId)
			->where($db->quoteName('ds_marco') . ' = ' . $db->quote($dsMarco))
			->order($db->quoteName('dt_marco') . ' DESC');
			;

		$db->setQuery($query);
		return $db->loadObject();
	}
	


	/**
	 * Get an instance of Table class
	 *
	 * @param   string $type   Name of the Table class to get an instance of.
	 * @param   string $prefix Prefix for the table class name. Optional.
	 * @param   array  $config Array of configuration values for the Table object. Optional.
	 *
	 * @return  Table|bool Table if success, false on failure.
	 */
	public function getTable($type = 'Adesao', $prefix = 'Administrator', $config = array())
	{
		return parent::getTable($type, $prefix, $config);
	}


	public function getMunicipiosAdesao($idAdesao)
	{
		$adesao = $this->getItem($idAdesao);
		$db = Factory::getContainer()->get('DatabaseDriver');

		$arrMunicipios = !empty($adesao->ds_municipios_cod_ibge) ? $adesao->ds_municipios_cod_ibge : '';
		if ($arrMunicipios === '') {
			return [];
		}

		//transformar os valores em inteiros para evitar SQL Injection
		$municipiosArray = array_map('intval', $arrMunicipios);

		$query = $db->getQuery(true);
		$query
			->select($db->quoteName('m.co_ibge') . ', ' . $db->quoteName('m.no_municipio') . ', ' . $db->quoteName('e.sg_estado') . ', ' . $db->quoteName('ma.ds_email'))
			->from($db->quoteName('#__tb_municipio', 'm'))
			->innerJoin($db->quoteName('#__tb_estado', 'e') . ' ON ' . $db->quoteName('e.co_estado') . ' = ' . $db->quoteName('m.co_estado'))
			->leftJoin($db->quoteName('#__tb_municipio_adesao', 'ma') . ' ON ' . $db->quoteName('ma.co_ibge') . ' = ' . $db->quoteName('m.co_ibge') . ' AND ' . $db->quoteName('ma.co_adesao') . ' = ' . (int) $idAdesao)
			->where($db->quoteName('m.co_ibge') . ' IN (' . implode(',', $municipiosArray) . ')');

		$db->setQuery($query);
		return $db->loadObjectList();
	}

	public function getFormulariosAbertos($editalId, $esfera = '')
	{
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$query
			->select('*')
			->from($db->quoteName('#__tb_edital'))
			->where($db->quoteName('co_edital_adesao') . ' = ' . (int) $editalId)
			->where($db->quoteName('tp_edital') . ' = ' . $db->quote('formulario'))
			->where($db->quoteName('state') . ' = 1')
			->where($db->quoteName('dt_encerramento') . ' > NOW()')
			->where($db->quoteName('dt_abertura') . ' <= NOW()');

		$emptyEsfera = '(' . $db->quoteName('tp_esfera_solicitante') . ' IS NULL OR '
			. $db->quoteName('tp_esfera_solicitante') . ' = ' . $db->quote('') . ')';

		if ($esfera === '' || $esfera === null)
		{
			$query->where($emptyEsfera);
		}
		else
		{
			$query->where('(' . $emptyEsfera . ' OR ' . $db->quoteName('tp_esfera_solicitante') . ' = ' . $db->quote($esfera) . ')');
		}

		$db->setQuery($query);
		return $db->loadObjectList();
	}
	

	public function getProgressoPreenchimentoAdesao($idAdesao, $idEdital) 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$query
			->select('COUNT(*) AS total, 
						SUM(CASE WHEN r.ds_resposta is not null  AND length(r.ds_resposta) >0 THEN 1 ELSE 0 END) AS respondidos')
			->from($db->quoteName('#__tb_pergunta', 'p'))
			->leftJoin($db->quoteName('#__tb_resposta', 'r') . ' ON ' . $db->quoteName('r.co_pergunta') . ' = ' . $db->quoteName('p.id')
				. ' AND ' . $db->quoteName('r.co_adesao') . ' = ' . (int) $idAdesao
				. ' AND ' . $db->quoteName('r.state') . ' = 1'
			)
			->where($db->quoteName('p.state') . ' = 1')
			->where($db->quoteName('p.tp_pergunta') . ' not in (' . $db->quote('informativo') . ')')
			->where($db->quoteName('p.co_edital') . ' = ' . (int) $idEdital);

		$db->setQuery($query);
		return $db->loadObject();
	}
}
