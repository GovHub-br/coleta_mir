<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Joomla\CMS\Router\Route;

/**
 * Formulariorespostas controller class.
 *
 * @since  1.0.0
 */
class FormulariosrespostasController extends FormController
{
	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional
	 * @param   array   $config  Configuration array for model. Optional
	 *
	 * @return  object	The model
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Formularioseespostas', $prefix = 'Site', $config = array())
	{
		return parent::getModel($name, $prefix, array('ignore_request' => true));
	}

	/**
	 * Method to download a resposta file
	 *
	 * @return  void
	 */
	public function downloadResposta()
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if (empty($user->id))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$respostaId = $this->input->getInt('resposta_id', 0);
		$inline = $this->input->getInt('inline', 0);

		if (!$respostaId)
		{
			throw new \Exception(Text::_('COM_EDITAL_ERROR_INVALID_PARAMETERS'), 400);
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);

			$query
				->select(['r.ds_resposta', 'p.tp_pergunta', 'a.created_by', 'r.co_adesao'])
				->from($db->quoteName('#__tb_resposta', 'r'))
				->leftJoin($db->quoteName('#__tb_pergunta', 'p') . ' ON p.id = r.co_pergunta')
				->leftJoin($db->quoteName('#__tb_adesao', 'a') . ' ON a.id = r.co_adesao')
				->where($db->quoteName('r.id') . ' = ' . $db->quote($respostaId))
				->where($db->quoteName('p.tp_pergunta') . ' = ' . $db->quote('arquivo'));
				;

			$db->setQuery($query);
			$resposta = $db->loadObject();

            $adesao = AdesaoHelper::getAdesaoById($resposta->co_adesao);

            $user = Factory::getApplication()->getIdentity();
            if((int) $user->id !== (int) $adesao->created_by) {
                throw new \Exception("Acesso negado", 403);
            }

			if (!$resposta || empty($resposta->ds_resposta))
			{
				throw new \Exception(Text::_('COM_EDITAL_ERROR_FILE_NOT_FOUND'), 404);
			}

			$filePath = $resposta->ds_resposta;

			if (!file_exists($filePath))
			{
				throw new \Exception(Text::_('COM_EDITAL_ERROR_FILE_NOT_FOUND'), 404);
			}

			$fileName = basename($filePath);
			$fileSize = filesize($filePath);
			$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
			$disposition = $inline ? 'inline' : 'attachment';

			header('Content-Type: ' . $mimeType);
			header('Content-Length: ' . $fileSize);
			header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
			header('Cache-Control: no-cache, no-store, must-revalidate');
			header('Pragma: no-cache');
			header('Expires: 0');

			readfile($filePath);
			$app->close();
		} catch (\Exception $e) {
			$app->enqueueMessage($e->getMessage(), 'error');
			$app->redirect(Route::_('index.php?option=com_edital&view=formulariosrespostas&id=' . $adesao->id, false));
		}
	}
}
