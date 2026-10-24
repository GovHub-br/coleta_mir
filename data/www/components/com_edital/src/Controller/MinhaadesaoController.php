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

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Comedital\Component\Edital\Site\Model\AdesaoModel;
use Exception;
use \Joomla\CMS\Application\SiteApplication;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Multilanguage;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Controller\BaseController;
use Comedital\Component\Edital\Site\Helper\EditalMailHelper;
use Comedital\Component\Edital\Site\Helper\EditalHelper;

/**
 * Adesao class.
 *
 * @since  1.6.0
 */
class MinhaadesaoController extends BaseController
{
	/**
	 * Recebe o upload de um documento para uma resposta em complementação via AJAX.
	 *
	 * @return  void
	 */
	public function uploadResposta()
	{
		// Protect against CSRF
		$this->checkToken();

		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		// Must be authenticated
		if ($user->guest) {
			echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
			$app->close();
			return;
		}

		$respostaId = $this->input->getInt('resposta_id', 0);

		if (!$respostaId) {
			echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
			$app->close();
			return;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			// Load resposta + pergunta + adesão ownership in one query
			$query = $db->getQuery(true)
				->select([
					'r.id',
					'r.co_adesao',
					'r.co_pergunta',
					'r.st_situacao',
					'p.co_edital',
					'p.ds_opcoes',
					'a.created_by',
					'r.dt_limite',
					'a.no_oficial'
				])
				->from($db->quoteName('#__tb_resposta', 'r'))
				->leftJoin($db->quoteName('#__tb_pergunta', 'p') . ' ON p.id = r.co_pergunta')
				->leftJoin($db->quoteName('#__tb_adesao',  'a') . ' ON a.id = r.co_adesao')
				->where($db->quoteName('r.id') . ' = ' . (int) $respostaId);

			$db->setQuery($query);
			$resposta = $db->loadObject();

			if (!$resposta) {
				echo json_encode(['success' => false, 'message' => 'Documento não encontrado.']);
				$app->close();
				return;
			}

			// Verify current user owns this adesão
			if ((int) $resposta->created_by !== (int) $user->id) {
				echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
				$app->close();
				return;
			}

			// Only allow upload for documents awaiting complementation
			if ($resposta->st_situacao !== 'complementacao') {
				echo json_encode(['success' => false, 'message' => 'Este documento não aceita novos envios no momento.']);
				$app->close();
				return;
			}

			//valida data limite de envio com gmt do joomla
			$tzOffset = Factory::getConfig()->get('offset');
			$now = Factory::getDate('now', $tzOffset);
			$limite = Factory::getDate($resposta->dt_limite);


			if (strtotime($now->format('Y-m-d H:i:s', true)) > strtotime($limite->format('Y-m-d H:i:s'))) {
				echo json_encode(['success' => false, 'message' => 'O prazo para envio deste documento já expirou.']);
				$app->close();
				return;
			}

			// Validate uploaded file
			$file = $this->input->files->get('arquivo', null, 'raw');

			if (!$file || !isset($file['tmp_name']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
				echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado ou erro no upload.']);
				$app->close();
				return;
			}

			// Validate against pergunta options (size, extension, MIME)
			if (!empty($resposta->ds_opcoes)) {
				PerguntaHelper::validaOpcoesArquivo($resposta, $file, json_decode($resposta->ds_opcoes));
			}

			// Build a minimal pergunta object for PerguntaHelper::trataUploadArquivo
			$pergunta           = new \stdClass();
			$pergunta->id       = $resposta->co_pergunta;
			$pergunta->co_edital = $resposta->co_edital;

			// Move file to upload directory and get final path
			$result  = PerguntaHelper::trataUploadArquivo($resposta->co_adesao, $pergunta, $file);
			$newPath = $result['ds_resposta'];

			// Update resposta: new file path + reset status to pendente
			$updateQuery = $db->getQuery(true)
				->update($db->quoteName('#__tb_resposta'))
				->set([
					$db->quoteName('ds_resposta')  . ' = ' . $db->quote($newPath),
					$db->quoteName('st_situacao')  . ' = ' . $db->quote('pendente'),
					$db->quoteName('dt_criacao')  . ' = NOW()',
					$db->quoteName('dt_alterado')  . ' = NOW()',
					$db->quoteName('dt_limite')  . ' = NULL',
					$db->quoteName('modified_by')  . ' = ' . $db->quote($user->id),
				])
				->where($db->quoteName('id') . ' = ' . (int) $respostaId);

			$db->setQuery($updateQuery);
			$db->execute();

			            
            //verifica se tem mais respostas para complementacao
			$adesaoId = $resposta->co_adesao;
            $AdesaoRespostaModel = new \Comedital\Component\Edital\Site\Model\AdesaorespostasModel();
            $resRespostasComplementacao = $AdesaoRespostaModel->getRespostasByAdesaoPorSituacao($adesaoId, 'adesao', 'complementacao');
            if(empty($resRespostasComplementacao)){
                //se não tiver mais respostas para complementação, atualiza o status da adesão para pendente
                $AdesaoModel = new AdesaoModel();
                $AdesaoModel->alterarAdesao($adesaoId, ['st_adesao' => 'pendente', 'dt_alterado' => (new \DateTime())->format('Y-m-d H:i:s'), 'modified_by' => $user->id]);
                $AdesaoModel->inserirMarcoTemporal($adesaoId, 'complementado', $user->id);

				$corpoEmail = EditalMailHelper::montarCorpoEmailGestao([
					'adesao_id' => $adesaoId,
					'nome_oficial' => $resposta->no_oficial,
					'site_name' => Factory::getConfig()->get('sitename', 'SINAPIR')
				], 'gestao_complementacao_recebida.html');

				$emailsGestao = EditalHelper::getEmailsGestores();
				foreach ($emailsGestao as $email) {
					EditalMailHelper::sendEmail(
						$email,
						'[' . Factory::getConfig()->get('sitename', 'SINAPIR') . '] Documentos Complementares Recebidos',
						$corpoEmail
					);
				}
            }

			echo json_encode(['success' => true, 'message' => 'Documento enviado com sucesso! Ele será analisado pela equipe técnica.']);
		} catch (\Exception $e) {
			echo json_encode(['success' => false, 'message' => $e->getMessage()]);
		}

		$app->close();
	}


	public function enviarRecurso()
	{
		// Protect against CSRF
		$this->checkToken();

		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		$adesaoId = $this->input->getInt('id_adesao', 0);

		try {

			$ModelAdesao = $this->getModel('Adesao');
			$adesao = $ModelAdesao->getItem($adesaoId);
			if (!$adesao || (int) $adesao->created_by !== (int) $user->id) {
				throw new Exception('Adesão não encontrada ou acesso negado.');
			}

			if ($adesao->st_adesao !== 'indeferido') {
				throw new Exception('Recurso só pode ser enviado para adesões indeferidas.');
			}


			$resPerguntas = PerguntaHelper::getPerguntasByEdital($adesao->co_edital_id, 'recurso');
			$inputPerguntas = $this->input->get('pergunta', [], 'array');
			$inputFiles     = $this->input->files->get('pergunta', null, 'raw');

			$data = PerguntaHelper::validarRespostasPerguntas(
				$resPerguntas,
				$inputPerguntas,
				$inputFiles,
				false,
				[],
				null,
				(string) ($adesao->tp_esfera_governamental ?? '')
			);


			// Save respostas - Pass the database connection to ensure transaction compliance
			if (!empty($data['respostas']) && !empty($adesaoId)) {
				foreach ($data['respostas'] as $perguntaId => $resposta) {

					// Pass the database connection to maintain transaction
					$result = PerguntaHelper::saveResposta($adesaoId, $perguntaId, $resposta);

					// If any response fails to save, rollback the entire transaction
					if ($result === false) {
						throw new Exception('Erro ao salvar respostas. Operação cancelada.');
					}
				}
			}

			$prazoRecurso = !empty($adesao->dt_limite_situacao) ? $adesao->dt_limite_situacao : date('Y-m-d H:i:s', strtotime('+30 days'));
			$data = ['st_adesao' => 'recurso', 'dt_limite_situacao' => $prazoRecurso, 'dt_alterado' => date('Y-m-d H:i:s'), 'modified_by' => $user->id];
			$table = $ModelAdesao->getTable();
			$table->load($adesaoId);
			$table->st_adesao          = $data['st_adesao'];
			$table->dt_limite_situacao = $data['dt_limite_situacao'];
			$table->dt_alterado        = $data['dt_alterado'];
			$table->modified_by        = $data['modified_by'];
			$table->store();

			$ModelAdesao->inserirMarcoTemporal($adesaoId, 'recurso', $user->id);


			$corpoEmail = EditalMailHelper::montarCorpoEmailGestao([
				'adesao_id' => $adesaoId,
				'nome_oficial' => $adesao->no_oficial,
				'site_name' => Factory::getConfig()->get('sitename', 'SINAPIR')
			], 'gestao_recurso_recebido.html');

			$emailsGestao = EditalHelper::getEmailsGestores();
			foreach ($emailsGestao as $email) {
				EditalMailHelper::sendEmail(
					$email,
					'[' . Factory::getConfig()->get('sitename', 'SINAPIR') . '] Recurso Recebido',
					$corpoEmail
				);
			}
            

			echo json_encode(['success' => true, 'message' => 'Recurso enviado com sucesso! Ele será analisado pela equipe técnica.']);
		} catch (\Exception $e) {
			echo json_encode(['success' => false, 'message' => $e->getMessage()]);
		}

		$app->close();
	}
}
