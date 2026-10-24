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
use \Comedital\Component\Edital\Site\Helper\PerguntaHelper;

/**
 * Adesaorespostas model.
 *
 * @since  1.0.0
 */
class AdesaorespostasModel extends ItemModel
{
	protected $_item;

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
		$this->setState('adesaorespostas.id', $id);

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
				$id = $this->getState('adesaorespostas.id');
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

			// Get the respostas for this adesao
			$respostas = $this->getRespostasByAdesao($id, $etapa);
			$this->_item->respostas = $respostas;
			$this->_item->finalizacao = (new AdesaoModel())->getResumoFinalizacao((int) $id, $etapa);

			// Get the edital data  
			$edital = $this->getEdital($this->_item->co_edital_id);
			$this->_item->edital = $edital;
		}

		return $this->_item;
	}

	/**
	 * Get adesao data
	 *
	 * @param   integer $id The adesao ID
	 *
	 * @return  mixed    Object on success, false on failure.
	 */
	public function getAdesao($id)
	{
		$adesaoModel = $this->bootComponent('com_edital')
			->getMVCFactory()
			->createModel('Adesao', 'Site');
		
		return $adesaoModel->getItem($id);
	}

	/**
	 * Get respostas by adesao ID with pergunta details
	 *
	 * @param   integer $adesaoId The adesao ID
	 *
	 * @return  array    Array of responses with pergunta details
	 */
	public function getRespostasByAdesao($adesaoId, $etapa = 'adesao')
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		$query
			->select([
				'r.*',
				'p.ds_titulo as pergunta_titulo',
				'p.tp_pergunta as pergunta_tipo',
				'p.tp_requerida as pergunta_obrigatoria',
				'p.ds_descricao as pergunta_descricao',
				'p.ds_opcoes as pergunta_opcoes',
				'p.ordering as pergunta_ordem',
				'p.co_agrupamento as co_agrupamento',
				'a.no_agrupamento as no_agrupamento'
			])
			->from($db->quoteName('#__tb_resposta', 'r'))
			->leftJoin($db->quoteName('#__tb_pergunta', 'p') . ' ON p.id = r.co_pergunta')
			->leftJoin($db->quoteName('#__tb_agrupamento', 'a') . ' ON a.id = p.co_agrupamento')
			->where($db->quoteName('r.co_adesao') . ' = ' . $db->quote($adesaoId))
			->where($db->quoteName('p.tp_etapa') . ' = '. $db->quote($etapa))
			->where($db->quoteName('r.state') . ' = 1')
			->order('a.ordering ASC, p.ordering ASC');

		$db->setQuery($query);
		$respostas = $db->loadObjectList();

		// Process file paths for arquivo type questions
		foreach ($respostas as $resposta)
		{
			if ($resposta->pergunta_tipo === 'arquivo' && !empty($resposta->ds_resposta))
			{
				// Convert absolute path to web accessible path
				$resposta->arquivo_url = $this->convertToWebPath($resposta->ds_resposta);
				$resposta->arquivo_nome = basename($resposta->ds_resposta);
				$resposta->arquivo_tamanho = filesize($resposta->ds_resposta);
				$resposta->arquivo_tamanho_formatado = $this->formatFileSize($resposta->arquivo_tamanho);
				$resposta->arquivo_tipo = mime_content_type( $resposta->ds_resposta);
				$resposta->url_download = $this->getUrlDownload($resposta->id);
				$resposta->url_preview = $this->getUrlPreview($resposta->id);
			}
		}

		return $respostas;
	}

	public function getUrlDownload($respostaId)
	{
		return \Joomla\CMS\Router\Route::_('index.php?option=com_edital&task=adesaorespostas.downloadResposta&resposta_id=' . $respostaId, false);
	}

	public function getUrlPreview($respostaId)
	{
		return \Joomla\CMS\Router\Route::_('index.php?option=com_edital&task=adesaorespostas.downloadResposta&inline=1&resposta_id=' . $respostaId, false);
	}

	public function formatFileSize($bytes, $precision = 2)
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$precision}f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
	}

	/**
	 * Get edital data
	 *
	 * @param   integer $editalId The edital ID
	 *
	 * @return  mixed    Object on success, false on failure.
	 */
	protected function getEdital($editalId)
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		$query
			->select('e.*')
			->from($db->quoteName('#__tb_edital', 'e'))
			->where($db->quoteName('e.id') . ' = ' . $db->quote($editalId));

		$db->setQuery($query);
		return $db->loadObject();
	}

	/**
	 * Method to get all adesoes for listing
	 *
	 * @return  array    Array of adesoes with basic information
	 *
	 * @throws Exception
	 */
	public function getAdesoesList()
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		$query
			->select([
				'a.id',
				'a.no_oficial',
				'a.ds_cnpj',
				'a.tp_esfera_governamental',
				'a.sg_estado',
				'a.ds_municipios',
				'a.dt_criacao',
				'a.st_adesao'
			])
			->from($db->quoteName('#__tb_adesao', 'a'))
			->leftJoin($db->quoteName('#__tb_edital', 'e') . ' ON e.id = a.co_edital')
			->where($db->quoteName('a.state') . ' = 1')
			->order('a.dt_criacao DESC');

		$db->setQuery($query);
		return $db->loadObjectList();
	}

	/**
	 * Convert absolute file path to web accessible path
	 *
	 * @param   string $absolutePath The absolute file path
	 *
	 * @return  string Web accessible path
	 */
	protected function convertToWebPath($absolutePath)
	{
		$uploadDir = realpath(JPATH_SITE . '/../uploads/edital/');
		
		if ($uploadDir && strpos($absolutePath, $uploadDir) === 0)
		{
			$relativePath = str_replace($uploadDir, '', $absolutePath);
			return '/uploads/edital' . str_replace('\\', '/', $relativePath);
		}

		return $absolutePath;
	}


	public function getRespostasByAdesaoPorSituacao($adesaoId, $etapa = 'adesao', $situacao = 'pendente')
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		$query
			->select([
				'r.*',
				'p.ds_titulo as pergunta_titulo',
				'p.tp_pergunta as pergunta_tipo',
				'p.tp_requerida as pergunta_obrigatoria',
				'p.ds_descricao as pergunta_descricao',
				'p.ds_opcoes as pergunta_opcoes',
				'p.ordering as pergunta_ordem'
			])
			->from($db->quoteName('#__tb_resposta', 'r'))
			->leftJoin($db->quoteName('#__tb_pergunta', 'p') . ' ON p.id = r.co_pergunta')
			->where($db->quoteName('r.co_adesao') . ' = ' . $db->quote($adesaoId))
			->where($db->quoteName('p.tp_etapa') . ' = '. $db->quote($etapa))
			->where($db->quoteName('r.state') . ' = 1')
			->where($db->quoteName('r.st_situacao') . ' = ' . $db->quote($situacao))
			->order('p.ordering ASC');

		$db->setQuery($query);
		$respostas = $db->loadObjectList();

		return $respostas;
	}
}
