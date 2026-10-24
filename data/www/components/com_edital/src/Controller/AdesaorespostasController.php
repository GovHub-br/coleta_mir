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

use Joomla\CMS\Application\SiteApplication;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Comedital\Component\Edital\Site\Model\AdesaoModel;
use Comedital\Component\Edital\Site\Model\MunicipioAdesaoModel;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Comedital\Component\Edital\Site\Model\RespostaModel;
use Comedital\Component\Edital\Site\Service\AdesaoEmailService;

/**
 * Adesaorespostas controller class.
 *
 * @since  1.0.0
 */
class AdesaorespostasController extends BaseController
{
    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types, for valid values see {@link InputFilter::clean()}.
     *
     * @return  BaseController  This object to support chaining.
     *
     * @since   1.0.0
     */
    public function display($cachable = false, $urlparams = array())
    {
        $cachable = true;

        // Set the default view name and format from the Request.
        $vName   = $this->input->get('view', 'adesaorespostas');
        $vFormat = 'html';
        $lName   = $this->input->get('layout', 'default');
        $id      = $this->input->getInt('id');

        // For list layout, we don't require ID or pubkey, but need admin permission
        if ($lName === 'list') {
            // Check admin permission for list view
            $user = $this->app->getIdentity();
            if (!$user->authorise('respostas.manage', 'com_edital')) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        } else {
            // Check for valid adesao ID for default layout
            if (!$id) {
                throw new \Exception(Text::_('COM_EDITAL_ERROR_ADESAO_NOT_FOUND'), 404);
            }

            // Validate access using pubkey
            $pubkey = $this->input->getString('pubkey', '');
            $expectedPubkey = AdesaoHelper::getAdesaoPubKey($id);

            if ($pubkey !== $expectedPubkey) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        }

        // Get/Create the view
        $view = $this->getView($vName, $vFormat, '', array('base_path' => $this->basePath, 'layout' => $lName));

        // Get/Create the model
        if ($model = $this->getModel($vName)) {
            // Push the model into the view (as default).
            if (method_exists($view, 'setModel')) {
                $view->setModel($model, true);
            }
        }

        // Display the view
        if ($cachable && $vName !== 'adesaorespostas') {
            $urlparams['id']     = 'INT';
            $urlparams['pubkey'] = 'ALNUM';
        }

        parent::display($cachable, $urlparams);

        return $this;
    }

    /**
     * Method to download a file attached to a resposta
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function downloadResposta()
    {
        $app = Factory::getApplication();

        // Get parameters
        $respostaId = $this->input->getInt('resposta_id', 0);
        $inline = $this->input->getInt('inline', 0);

        // Validate parameters
        if (!$respostaId) {
            throw new \Exception(Text::_('COM_EDITAL_ERROR_INVALID_PARAMETERS'), 400);
        }

        try {
            // Get the resposta
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $query
                ->select('r.ds_resposta, p.tp_pergunta')
                ->from($db->quoteName('#__tb_resposta', 'r'))
                ->leftJoin($db->quoteName('#__tb_pergunta', 'p') . ' ON p.id = r.co_pergunta')
                ->where($db->quoteName('r.id') . ' = ' . $db->quote($respostaId))
                ->where($db->quoteName('p.tp_pergunta') . ' = ' . $db->quote('arquivo'));

            $db->setQuery($query);
            $resposta = $db->loadObject();

            if (!$resposta || empty($resposta->ds_resposta)) {
                throw new \Exception(Text::_('COM_EDITAL_ERROR_FILE_NOT_FOUND'), 404);
            }

            $filePath = $resposta->ds_resposta;

            // Check if file exists
            if (!file_exists($filePath)) {
                throw new \Exception(Text::_('COM_EDITAL_ERROR_FILE_NOT_FOUND'), 404);
            }

            // Get file info
            $fileName = basename($filePath);
            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

            // Set headers for download or inline view
            $disposition = $inline ? 'inline' : 'attachment';
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $fileSize);

            // Clean output buffer
            ob_clean();
            flush();

            // Read and output file
            readfile($filePath);

            $app->close();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Gera o Formulário de Adesão preenchido em PDF, para anexação ao processo SEI do ente.
     * Disponível para a equipe de gestão e para o próprio ente que criou a adesão.
     *
     * @return  void
     */
    public function formulariopdf()
    {
        $this->checkToken('GET');

        $id = $this->input->getInt('id', 0);

        if (!$id) {
            throw new \Exception(Text::_('COM_EDITAL_ERROR_ADESAO_NOT_FOUND'), 404);
        }

        $item = $this->getModel('Adesaorespostas')->getItem($id);
        $user = $this->app->getIdentity();

        $isGestao = $user->authorise('respostas.manage', 'com_edital');
        $isDono   = !$user->guest && (int) $item->created_by === (int) $user->id;

        if (!$isGestao && !$isDono) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $protocolo = AdesaoHelper::getProtocoloAdesao($id);

        if ($this->input->getCmd('modelo') === 'anexo2') {
            AdesaoHelper::downloadHtmlPdf(
                'Anexo II - ' . $protocolo,
                AdesaoHelper::renderFormularioAnexoIIHtml($item),
                'Anexo_II_' . $protocolo . '.pdf',
                'P'
            );

            return;
        }

        $municipios = (new MunicipioAdesaoModel())->getMunicipiosByAdesao($id);

        AdesaoHelper::downloadHtmlPdf(
            'Formulário de Adesão - ' . $protocolo,
            AdesaoHelper::renderFormularioAdesaoHtml($item, $municipios),
            'Formulario_Adesao_' . $protocolo . '.pdf',
            'P'
        );
    }

    public function aprovarResposta()
    {
        $app = Factory::getApplication();

        // Get parameters
        $respostaId = $this->input->getInt('resposta_id', 0);
        $user = $app->getIdentity();

        if (!$user->authorise('respostas.manage', 'com_edital')) {
            throw new \Exception("Não Autorizado.", 403);
        }

        // Validate parameters
        if (!$respostaId) {
            throw new \Exception(Text::_('COM_EDITAL_ERROR_INVALID_PARAMETERS'), 400);
        }

		try {
			// Update resposta status to 'deferido'
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);

            $fields = array(
                $db->quoteName('st_situacao') . ' = ' . $db->quote('aprovado'),
                $db->quoteName('dt_alterado') . ' = NOW()',
                $db->quoteName('modified_by') . ' = ' . $db->quote($user->id),
            );

            $conditions = array(
                $db->quoteName('id') . ' = ' . $db->quote($respostaId)
            );

			$query->update($db->quoteName('#__tb_resposta'))->set($fields)->where($conditions);
			$db->setQuery($query);
			$db->execute();

			$finalizacao = $this->getResumoFinalizacaoByRespostaId($respostaId);

			//json output
			echo json_encode(array(
				'success' => true,
				'message' => $this->formatToastMessage('Documento aprovado.', 'Situação atualizada com sucesso'),
				'messageType' => 'success',
				'finalizacao' => $finalizacao,
			));
		} catch (\Exception $e) {
            //json output
            echo json_encode(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
        $app->close();
    }

    public function rejeitarResposta()
    {
        $app = Factory::getApplication();

        $respostaId = $this->input->getInt('resposta_id', 0);
        $motivo     = trim($this->input->getString('motivo', ''));
        $user       = $app->getIdentity();

        if (!$user->authorise('respostas.manage', 'com_edital')) {
            echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
            $app->close();
            return;
        }

        if (!$respostaId || $motivo === '') {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            $app->close();
            return;
        }

        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('st_situacao')             . ' = ' . $db->quote('rejeitado'),
                $db->quoteName('ds_comentario_avaliador') . ' = ' . $db->quote($motivo),
                $db->quoteName('dt_alterado')             . ' = NOW()',
                $db->quoteName('modified_by')             . ' = ' . $db->quote($user->id),
            ];

			$query->update($db->quoteName('#__tb_resposta'))
			      ->set($fields)
			      ->where($db->quoteName('id') . ' = ' . $db->quote($respostaId));

			$db->setQuery($query);
			$db->execute();

			$finalizacao = $this->getResumoFinalizacaoByRespostaId($respostaId);

			echo json_encode([
				'success' => true,
				'message' => $this->formatToastMessage('Documento rejeitado.', 'Situação atualizada com sucesso'),
				'messageType' => 'error',
				'finalizacao' => $finalizacao,
			]);
		} catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }

    public function reabrirResposta()
    {
        $app = Factory::getApplication();

        $respostaId = $this->input->getInt('resposta_id', 0);
        $user = $app->getIdentity();

        if (!$user->authorise('respostas.manage', 'com_edital')) {
            echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
            $app->close();
            return;
        }

        if (!$respostaId) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            $app->close();
            return;
        }

        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('st_situacao')             . ' = ' . $db->quote('pendente'),
                $db->quoteName('ds_comentario_avaliador') . ' = NULL',
                $db->quoteName('dt_limite')               . ' = NULL',
                $db->quoteName('dt_alterado')             . ' = ' . $db->quote(Factory::getDate()->toSql()),
                $db->quoteName('modified_by')             . ' = ' . $db->quote($user->id),
            ];

            $query->update($db->quoteName('#__tb_resposta'))
                ->set($fields)
                ->where($db->quoteName('id') . ' = ' . $db->quote($respostaId))
                ->where($db->quoteName('st_situacao') . ' = ' . $db->quote('rejeitado'));

            $db->setQuery($query);
            $db->execute();

            echo json_encode(['success' => true, 'message' => 'Resposta movida para pendente com sucesso.']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao reabrir resposta.']);
        }

        $app->close();
    }

    public function complementarResposta()
    {
        $app = Factory::getApplication();

        $respostaId = $this->input->getInt('resposta_id', 0);
        $mensagem   = trim($this->input->getString('mensagem', ''));
        $user       = $app->getIdentity();

        if (!$user->authorise('respostas.manage', 'com_edital')) {
            echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
            $app->close();
            return;
        }

        if (!$respostaId || $mensagem === '') {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            $app->close();
            return;
        }

        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $tzOffset = Factory::getConfig()->get('offset');

            $query = $db->getQuery(true);
            $diasComplementacao = $this->getDiasComplementacaoByRespostaId($respostaId);
            $dtLimiteResposta = Factory::getDate('now', $tzOffset)->modify('+' . $diasComplementacao . ' days')->format('Y-m-d 23:59:59', true );

            $fields = [
                $db->quoteName('st_situacao')             . ' = ' . $db->quote('complementacao'),
                $db->quoteName('ds_comentario_avaliador') . ' = ' . $db->quote($mensagem),
                $db->quoteName('dt_alterado')             . ' = NOW()',
                $db->quoteName('dt_limite')               . ' = ' . $db->quote($dtLimiteResposta),
                $db->quoteName('modified_by')             . ' = ' . $db->quote($user->id),
            ];

            $query->update($db->quoteName('#__tb_resposta'))
                  ->set($fields)
                  ->where($db->quoteName('id') . ' = ' . $db->quote($respostaId));

            $db->setQuery($query);
            $db->execute();

            $adesaoId = $this->getAdesaoIdByRespostaId($respostaId);
            if ($adesaoId) {
                $AdesaoModel = new AdesaoModel();
                $AdesaoModel->inserirMarcoTemporal($adesaoId, 'complementacao', $user->id);
                $dtLimiteAdesao = $this->getMaxDtLimiteComplementacaoByAdesao($adesaoId) ?: $dtLimiteResposta;
                
                $dadosAlterar = [
                    'st_adesao' => 'complementacao',
                    'dt_alterado' => Factory::getDate()->toSql(),
                    'dt_limite_situacao' => $dtLimiteAdesao,
                    'modified_by' => $user->id,
                ];

                $AdesaoModel->alterarAdesao($adesaoId, $dadosAlterar);

                $Resposta = new RespostaModel();
                $resRepostaObj = $Resposta->getItem($respostaId); // Ensure resposta is loaded in model for email context
                
                $this->enviarEmailEvento(
                    $adesaoId,
                    'complementacao_documento.html',
                    'Solicitação de Complementação de Documento',
                    [
                        '{{STATUS}}'             => 'Complementação Solicitada',
                        '{{MENSAGEM_AVALIADOR}}' => nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8')),
                        '{{PERGUNTA}}'           => isset($resRepostaObj->pergunta->ds_titulo) ? $resRepostaObj->pergunta->ds_titulo : 'N/A',
                    ]
				);
			}

			$finalizacao = $this->getResumoFinalizacaoByRespostaId($respostaId);
			$nomeAdesao = $adesaoId ? ((new AdesaoModel())->getItem($adesaoId)?->no_oficial ?? '') : '';

			echo json_encode([
				'success' => true,
				'message' => $this->formatToastMessage(
					'Solicitação de complementação enviada.',
					'Notificação enviada para ' . $nomeAdesao
				),
				'messageType' => 'info',
				'finalizacao' => $finalizacao,
			]);
		} catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }

    public function avaliarRecurso(){
        $app = Factory::getApplication();

        $idAdesao = $this->input->getInt('id_adesao', 0);
        $acao     = $this->input->getString('acao', '');
        $user      = $app->getIdentity();

        if (!$user->authorise('respostas.manage', 'com_edital')) {
            echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
            $app->close();
            return;
        }

        if (!$idAdesao || !in_array($acao, ['deferir', 'indeferir'])) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            $app->close();
            return;
        }

        try {
            $AdesaoModel = new AdesaoModel();

            $adesao = $AdesaoModel->getItem($idAdesao); // Ensure adesao exists and is loaded in model

            if($adesao->st_adesao !== 'recurso') {
                throw new \Exception('Apenas adesões em recurso podem ser avaliadas.');
            }

            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $resPerguntas = PerguntaHelper::getPerguntasByEdital($adesao->co_edital_id, 'avaliacao_recurso');
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

            if(!empty($data['errors'])){
                throw new \Exception(implode("\n", $data['errors']));
            }

			// Save respostas - Pass the database connection to ensure transaction compliance
			if (!empty($data['respostas']) && !empty($idAdesao)) {
				foreach ($data['respostas'] as $perguntaId => $resposta) {

					// Pass the database connection to maintain transaction
					$result = PerguntaHelper::saveResposta($idAdesao, $perguntaId, $resposta);

					// If any response fails to save, rollback the entire transaction
					if ($result === false) {
						throw new Exception('Erro ao salvar respostas. Operação cancelada.');
					}
				}
			}

            // Update adesao status
            $fields = [
                $db->quoteName('st_adesao') . ' = ' . $db->quote($acao === 'deferir' ? 'deferido' : 'indeferido'),
                $db->quoteName('dt_alterado') . ' = NOW()',
                $db->quoteName('modified_by') . ' = ' . $db->quote($user->id),
            ];

            $query->update($db->quoteName('#__tb_adesao'))
                  ->set($fields)
                  ->where($db->quoteName('id') . ' = ' . $db->quote($idAdesao));

            $db->setQuery($query);
            $db->execute();

            
            if($acao === 'deferir') {
                $AdesaoModel->inserirMarcoTemporal($idAdesao, 'deferido', $user->id);
            }
            $AdesaoModel->inserirMarcoTemporal($idAdesao, $acao === 'deferir' ? 'recurso_deferido' : 'recurso_indeferido', $user->id);

            $templateRecurso = $acao === 'deferir' ? 'recurso_deferido.html' : 'recurso_indeferido.html';
            $assuntoRecurso  = $acao === 'deferir' ? 'Recurso Deferido — Adesão Aprovada' : 'Recurso Indeferido';
            $statusRecurso   = $acao === 'deferir' ? 'Recurso Deferido' : 'Recurso Indeferido';
            $this->enviarEmailEvento($idAdesao, $templateRecurso, $assuntoRecurso, ['{{STATUS}}' => $statusRecurso]);

            echo json_encode([
                'success' => true,
                'message' => $this->formatToastMessage(
                    $acao === 'deferir' ? 'Recurso deferido.' : 'Recurso indeferido.',
                    'Decisão registrada para ' . $adesao->no_oficial
                ),
                'messageType' => $acao === 'deferir' ? 'success' : 'error',
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }

    /**
     * Retorna a quantidade de dias de complementacao configurada para a pergunta da resposta.
     */
    private function getDiasComplementacaoByRespostaId(int $respostaId): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select($db->quoteName('p.qt_dias_complementacao'))
            ->from($db->quoteName('#__tb_resposta', 'r'))
            ->innerJoin($db->quoteName('#__tb_pergunta', 'p') . ' ON p.id = r.co_pergunta')
            ->where($db->quoteName('r.id') . ' = ' . $db->quote($respostaId));

        $db->setQuery($query);
        $dias = (int) $db->loadResult();

        return $dias > 0 ? $dias : 5;
    }

    /**
     * Retorna o maior dt_limite ativo das respostas em complementacao de uma adesao.
     */
    private function getMaxDtLimiteComplementacaoByAdesao(int $adesaoId): ?string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select('MAX(' . $db->quoteName('dt_limite') . ')')
            ->from($db->quoteName('#__tb_resposta'))
            ->where($db->quoteName('co_adesao') . ' = ' . $db->quote($adesaoId))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('st_situacao') . ' = ' . $db->quote('complementacao'))
            ->where($db->quoteName('dt_limite') . ' IS NOT NULL');

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result ?: null;
    }

    /**
     * Retorna o co_adesao vinculado a uma resposta.
     */
    private function getAdesaoIdByRespostaId(int $respostaId): int
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select($db->quoteName('co_adesao'))
              ->from($db->quoteName('#__tb_resposta'))
              ->where($db->quoteName('id') . ' = ' . $db->quote($respostaId));
        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    private function getResumoFinalizacaoByRespostaId(int $respostaId): ?object
    {
        $adesaoId = $this->getAdesaoIdByRespostaId($respostaId);

        if (!$adesaoId) {
            return null;
        }

        return (new AdesaoModel())->getResumoFinalizacao($adesaoId);
    }

    private function formatToastMessage(string $title, string $detail): string
    {
        return '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong> '
            . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    }
    /**
     * Envia email de notificação de evento para o responsável da adesão.
     *
     * @param int    $adesaoId          ID da adesão
     * @param string $templateName      Nome do arquivo de template (dentro de tmpl/email/)
     * @param string $assunto           Assunto do email (sem o prefixo do site)
     * @param array  $extraPlaceholders Placeholders extras específicos do evento
     */
    private function enviarEmailEvento(int $adesaoId, string $templateName, string $assunto, array $extraPlaceholders = []): void
    {
        try {
            $emailService = new AdesaoEmailService();
            $emailService->enviarEmailEvento($adesaoId, $templateName, $assunto, $extraPlaceholders);
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Operação realizada com sucesso, mas houve problema no envio do email: ' . $e->getMessage(),
                'warning'
            );
        }
    }
}
