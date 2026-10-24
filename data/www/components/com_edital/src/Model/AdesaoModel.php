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
class AdesaoModel extends ItemModel
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
		$app  = Factory::getApplication('com_edital');
		$user = $app->getIdentity();

		// Check published state
		if ((!$user->authorise('core.edit.state', 'com_edital')) && (!$user->authorise('core.edit', 'com_edital')))
		{
			$this->setState('filter.published', 1);
			$this->setState('filter.archived', 2);
		}

		// Load state from the request userState on edit or from the passed variable on default
		if (Factory::getApplication()->input->get('layout') == 'edit')
		{
			$id = Factory::getApplication()->getUserState('com_edital.edit.adesao.id');
		}
		else
		{
			$id = Factory::getApplication()->input->get('id');
			Factory::getApplication()->setUserState('com_edital.edit.adesao.id', $id);
		}

		$this->setState('adesao.id', $id);

		// Load the parameters.
		$params       = $app->getParams();
		$params_array = $params->toArray();

		if (isset($params_array['item_id']))
		{
			$this->setState('adesao.id', $params_array['item_id']);
		}

		$this->setState('params', $params);
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
		if ($this->_item === null)
		{
			$this->_item = false;

			if (empty($id))
			{
				$id = $this->getState('adesao.id');
			}

			// Get a level row instance.
			$table = $this->getTable();

			// Attempt to load the row.
			if ($table && $table->load($id))
			{
				

				// Check published state.
				if ($published = $this->getState('filter.published'))
				{
					if (isset($table->state) && $table->state != $published)
					{
						throw new \Exception(Text::_('COM_EDITAL_ITEM_NOT_LOADED'), 403);
					}
				}

				// Convert the Table to a clean CMSObject.
				$properties  = $table->getProperties(1);
				$this->_item = ArrayHelper::toObject($properties, CMSObject::class);

				
			}

			if (empty($this->_item))
			{
				throw new \Exception(Text::_('COM_EDITAL_ITEM_NOT_LOADED'), 404);
			}
		}

		

		 $container = \Joomla\CMS\Factory::getContainer();

		 $userFactory = $container->get(UserFactoryInterface::class);

		if (isset($this->_item->created_by))
		{
			$user = $userFactory->loadUserById($this->_item->created_by);
			$this->_item->created_by_name = $user->name;
		}

		 $container = \Joomla\CMS\Factory::getContainer();

		 $userFactory = $container->get(UserFactoryInterface::class);

		if (isset($this->_item->modified_by))
		{
			$user = $userFactory->loadUserById($this->_item->modified_by);
			$this->_item->modified_by_name = $user->name;
		}

		if (isset($this->_item->co_edital) && $this->_item->co_edital != '')
		{
			if (is_object($this->_item->co_edital))
			{
				$this->_item->co_edital = ArrayHelper::fromObject($this->_item->co_edital);
			}

			$values = (is_array($this->_item->co_edital)) ? $this->_item->co_edital : explode(',',$this->_item->co_edital);

			$textValue = array();

			foreach ($values as $value)
			{
				$db    = $this->getDbo();
				$query = $db->getQuery(true);

				$query
					->select('`#__tb_edital_4309979`.`no_edital`')
					->from($db->quoteName('#__tb_edital', '#__tb_edital_4309979'))
					->where($db->quoteName('id') . ' = ' . $db->quote($value));

				$db->setQuery($query);
				$results = $db->loadObject();

				if ($results)
				{
					$textValue[] = $results->no_edital;
				}
			}

			$this->_item->co_edital_id = $this->_item->co_edital;
			$this->_item->co_edital = !empty($textValue) ? implode(', ', $textValue) : $this->_item->co_edital;

		}

		if (isset($this->_item->sg_estado) && $this->_item->sg_estado != '')
		{
			if (is_object($this->_item->sg_estado))
			{
				$this->_item->sg_estado = ArrayHelper::fromObject($this->_item->sg_estado);
			}

			$values = (is_array($this->_item->sg_estado)) ? $this->_item->sg_estado : explode(',',$this->_item->sg_estado);

			$textValue = array();

			foreach ($values as $value)
			{
				$db    = $this->getDbo();
				$query = "select sg_estado as value, no_estado key_field from #__tb_estado WHERE sg_estado = '$value' ";

				$db->setQuery($query);
				$results = $db->loadObject();

				if ($results)
				{
					$textValue[] = $results->key_field;
				}
			}

			$this->_item->sg_estado_uf = $this->_item->sg_estado;
			$this->_item->sg_estado = !empty($textValue) ? implode(', ', $textValue) : $this->_item->sg_estado;
		}

		if (isset($this->_item->ds_municipios) && $this->_item->ds_municipios != '')
		{
			if (is_object($this->_item->ds_municipios))
			{
				$this->_item->ds_municipios = ArrayHelper::fromObject($this->_item->ds_municipios);
			}
			
			$values = (is_array($this->_item->ds_municipios)) ? $this->_item->ds_municipios : explode(',', $this->_item->ds_municipios);
			//clonar valor original para não perder o valor do cod_ibge
			$this->_item->ds_municipios_cod_ibge = $values;

			$textValue = array();

			if (!empty($values))
			{
				$db           = $this->getDbo();
				$quotedValues = array_map([$db, 'quote'], $values);
				$query        = "SELECT CONCAT(no_municipio, ' (', sg_estado, ')') key_field"
					. " FROM #__tb_municipio m"
					. " INNER JOIN #__tb_estado e ON e.co_estado = m.co_estado"
					. " WHERE m.co_ibge IN (" . implode(',', $quotedValues) . ")"
					. " ORDER BY sg_estado, no_municipio";

				$db->setQuery($query);
				$results = $db->loadColumn();

				if ($results)
				{
					$textValue = $results;
				}
			}

			$this->_item->ds_municipios = !empty($textValue) ? implode(', ', $textValue) : $this->_item->ds_municipios;
		}

		if (isset($this->_item->ds_modalidade) && $this->_item->ds_modalidade != ''){
			$this->_item->ds_modalidade_label = $this->getModalidadeLabel($this->_item->ds_modalidade);
		}

		return $this->_item;
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

	/**
	 * Get the id of an item by alias
	 * @param   string $alias Item alias
	 *
	 * @return  mixed
	 * 
	 * @deprecated  No replacement
	 */
	public function getItemIdByAlias($alias)
	{
		$table      = $this->getTable();
		$properties = $table->getProperties();
		$result     = null;
		$aliasKey   = null;
		if (method_exists($this, 'getAliasFieldNameByView'))
		{
			$aliasKey   = $this->getAliasFieldNameByView('adesao');
		}
		

		if (key_exists('alias', $properties))
		{
			$table->load(array('alias' => $alias));
			$result = $table->id;
		}
		elseif (isset($aliasKey) && key_exists($aliasKey, $properties))
		{
			$table->load(array($aliasKey => $alias));
			$result = $table->id;
		}
		
			return $result;
		
	}

	/**
	 * Method to check in an item.
	 *
	 * @param   integer $id The id of the row to check out.
	 *
	 * @return  boolean True on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function checkin($id = null)
	{
		// Get the id.
		$id = (!empty($id)) ? $id : (int) $this->getState('adesao.id');
				
		if ($id)
		{
			// Initialise the table
			$table = $this->getTable();

			// Attempt to check the row in.
			if (method_exists($table, 'checkin'))
			{
				if (!$table->checkin($id))
				{
					return false;
				}
			}
		}

		return true;
		
	}

	/**
	 * Method to check out an item for editing.
	 *
	 * @param   integer $id The id of the row to check out.
	 *
	 * @return  boolean True on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function checkout($id = null)
	{
		// Get the user id.
		$id = (!empty($id)) ? $id : (int) $this->getState('adesao.id');

				
		if ($id)
		{
			// Initialise the table
			$table = $this->getTable();

			// Get the current user object.
			$user = Factory::getApplication()->getIdentity();

			// Attempt to check the row out.
			if (method_exists($table, 'checkout'))
			{
				if (!$table->checkout($user->get('id'), $id))
				{
					return false;
				}
			}
		}

		return true;
				
	}

	/**
	 * Publish the element
	 *
	 * @param   int $id    Item id
	 * @param   int $state Publish state
	 *
	 * @return  boolean
	 */
	public function publish($id, $state)
	{
		$table = $this->getTable();
				
		$table->load($id);
		$table->state = $state;

		return $table->store();
				
	}

	/**
	 * Method to delete an item
	 *
	 * @param   int $id Element id
	 *
	 * @return  bool
	 */
	public function delete($id)
	{
		$table = $this->getTable();

		
			return $table->delete($id);
		
	}

	/**
	 * Method to get the label of modalidade
	 *
	 * @param   string $value Value of modalidade
	 *
	 * @return  string Label of modalidade
	 */
	public function getModalidadeLabel($value)
	{
		$xml = simplexml_load_file(JPATH_ADMINISTRATOR . '/components/com_edital/forms/adesao.xml');
		
		if ($xml === false)
		{
			return $value;
		}

		$fields = $xml->xpath('//field[@name="ds_modalidade"]');
		
		if (empty($fields))
		{
			return $value;
		}

		$campoModalidade = $fields[0];

		foreach ($campoModalidade->option as $option)
		{
			$optionValue = (string) $option->attributes()->value;
			
			if ($optionValue === $value)
			{
				return (string) $option;
			}
		}

		return $value;
	}

	public function getResumoFinalizacao($adesaoId, $etapa = 'adesao')
	{
		$resumo = (object) [
			'totalDocs' => 0,
			'aprovados' => 0,
			'rejeitados' => 0,
			'pendentes' => 0,
			'complementacoes' => 0,
			'podeFinalizar' => false,
			'motivoBloqueio' => 'A solicitação só pode ser finalizada quando todos os documentos estiverem com status Aprovado ou Rejeitado.',
		];

		$db = $this->getDbo();
		$query = $db->getQuery(true);

		$query
			->select([
				$db->quoteName('r.st_situacao'),
				$db->quoteName('p.tp_pergunta'),
			])
			->from($db->quoteName('#__tb_resposta', 'r'))
			->innerJoin($db->quoteName('#__tb_pergunta', 'p') . ' ON p.id = r.co_pergunta')
			->where($db->quoteName('r.co_adesao') . ' = ' . $db->quote((int) $adesaoId))
			->where($db->quoteName('r.state') . ' = 1')
			->where($db->quoteName('p.tp_etapa') . ' = ' . $db->quote($etapa))
			->where($db->quoteName('p.tp_pergunta') . ' <> ' . $db->quote('informativo'));

		$db->setQuery($query);
		$respostas = $db->loadObjectList();

		foreach ($respostas as $resposta)
		{
			$resumo->totalDocs++;

			$situacao = strtolower(trim((string) ($resposta->st_situacao ?? '')));

			if (in_array($situacao, ['aprovado', 'aprovada'], true))
			{
				$resumo->aprovados++;
				continue;
			}

			if (in_array($situacao, ['rejeitado', 'rejeitada', 'reprovada'], true))
			{
				$resumo->rejeitados++;
				continue;
			}

			if ($situacao === 'complementacao')
			{
				$resumo->complementacoes++;
				continue;
			}

			$resumo->pendentes++;
		}

		$resumo->podeFinalizar = $resumo->totalDocs > 0
			&& ($resumo->aprovados + $resumo->rejeitados) === $resumo->totalDocs;

		$resumo->motivoBloqueio = $this->buildMotivoBloqueioFinalizacao($resumo);

		return $resumo;
	}

	private function buildMotivoBloqueioFinalizacao(object $resumo): string
	{
		if ($resumo->podeFinalizar)
		{
			return '';
		}

		$partes = [];

		if ($resumo->pendentes > 0)
		{
			$partes[] = $resumo->pendentes . ' documento(s) pendente(s) de avaliação';
		}

		if ($resumo->complementacoes > 0)
		{
			$partes[] = $resumo->complementacoes . ' documento(s) em complementação';
		}

		if (empty($partes))
		{
			return 'A solicitação só pode ser finalizada quando todos os documentos estiverem com status Aprovado ou Rejeitado.';
		}

		return 'A solicitação só pode ser finalizada quando todos os documentos estiverem com status Aprovado ou Rejeitado. Ainda há ' . implode(' e ', $partes) . '.';
	}

	/**
	 * Insere um marco temporal para uma adesão
	 *
	 * @param int    $adesaoId  ID da adesão
	 * @param string $marco     Descrição do marco (ex: 'indeferido', 'deferido', 'complementacao', 'recurso')
	 * @param int    $userId    ID do usuário que criou o marco
	 *
	 * @return bool  True se sucesso, false se falhar
	 */
	public function inserirMarcoTemporal($adesaoId, $marco, $userId)
	{
		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			
			
			$query = $db->getQuery(true);
			$query->insert($db->quoteName('#__tb_marco_temporal'))
				->columns([
					$db->quoteName('created_by'),
					$db->quoteName('dt_marco'),
					$db->quoteName('co_adesao'),
					$db->quoteName('ds_marco')
				])
				->values(
					$db->quote($userId) . ', ' .
					'NOW(), ' .
					$db->quote($adesaoId) . ', ' .
					$db->quote($marco)
				);

			$db->setQuery($query);
			return $db->execute();
		} catch (\Exception $e) {
			// Log error but don't fail the main operation
			Factory::getApplication()->enqueueMessage(
				'Erro ao registrar marco temporal: ' . $e->getMessage(),
				'warning'
			);
			return false;
		}
	}

	public function alterarAdesao($adesaoId, $dadosAlterar){
		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			
			$query = $db->getQuery(true);
			$fields = [];
			foreach ($dadosAlterar as $key => $value) {
				$fields[] = $db->quoteName($key) . ' = ' . $db->quote($value);
			}

			$query->update($db->quoteName('#__tb_adesao'))
				->set($fields)
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));

			$db->setQuery($query);
			return $db->execute();
		} catch (\Exception $e) {
			// Log error but don't fail the main operation
			Factory::getApplication()->enqueueMessage(
				'Erro ao alterar adesão: ' . $e->getMessage(),
				'warning'
			);
			return false;
		}
	}

	public function getAdesaoByUserEdital($userId, $editalId){
		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			
			$query = $db->getQuery(true);
			$query->select('*')
				->from($db->quoteName('#__tb_adesao'))
				->where($db->quoteName('created_by') . ' = ' . $db->quote($userId))
				->where($db->quoteName('co_edital') . ' = ' . $db->quote($editalId))
				->where($db->quoteName('state') . ' = 1')
				->order($db->quoteName('id') . ' DESC');

			$db->setQuery($query);
			return $db->loadObject();
		} catch (\Exception $e) {
			// Log error but don't fail the main operation
			Factory::getApplication()->enqueueMessage(
				'Erro ao buscar adesão: ' . $e->getMessage(),
				'warning'
			);
			return null;
		}
	}
	
}
