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
use Comedital\Component\Edital\Site\Model\AdesaogestaoModel;
use \Joomla\CMS\Application\SiteApplication;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Multilanguage;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Controller\BaseController;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Uri\Uri;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\HTML\HTMLHelper;
use Comedital\Component\Edital\Site\Service\AdesaoEmailService;
use \Comedital\Component\Edital\Site\Model\FormularioadesoesModel;
use Comedital\Component\Edital\Site\Helper\EditalMailHelper;
use Comedital\Component\Edital\Site\Model\MinhaadesaoModel;

/**
 * Adesao class.
 *
 * @since  1.6.0
 */
class FormularioadesoesController extends BaseController
{

	public function buscar()
	{
		$list = $this->loadFilteredList();

		header('Content-Type: application/json');
		echo json_encode(['items' => $list['items'], 'total' => $list['total']]);
		Factory::getApplication()->close();
	}


	private function loadFilteredList(?int $limit = null, ?int $start = null): array
	{
		$input      = Factory::getApplication()->input;
		$editalId	= $input->getInt('id_edital', 0);
		$searchTerm = $input->get('busca', '', 'STRING');
		$situacao   = $input->get('situacao', '', 'STRING');
		$ente       = $input->get('ente', '', 'STRING');
		$ordem      = $input->get('ordem', '', 'STRING');

		if ($limit === null) {
			$limit = AdesaoHelper::storeListLimit(
				'com_edital.adesaogestao.list.limit',
				$input->getInt('limit', 10)
			);
			$start = $limit > 0 ? max(0, $input->getInt('start', 0)) : 0;
		}

		switch ($ordem) {
			case 'dt_criacao desc':
				$ordem    = 'dt_criacao';
				$direcao  = 'DESC';
				break;
			case 'dt_criacaoasc':
				$ordem    = 'dt_criacao';
				$direcao  = 'ASC';
				break;
			default:
				$ordem    = 'dt_criacao';
				$direcao  = 'DESC';
				break;
		}

		$model  = new FormularioadesoesModel();
		$total  = $model->count($editalId, $searchTerm, $situacao, $ente);
		$result = $model->buscar($editalId, $searchTerm, $situacao, $ente, $ordem, $direcao, $limit, $start);

		foreach ($result as $item) {
			$item->dt_criacao_formatado = HTMLHelper::_('date', $item->dt_criacao, 'd/m/Y');
			$item->nr_protocolo = AdesaoHelper::getProtocoloAdesao($item->id);
			$item->municipios = AdesaoHelper::getMunicipiosAdesao($item->ds_municipios);
			$item->ds_modalidade_label = AdesaoHelper::getModalidadeLabel($item->ds_modalidade ?? '');
			$MinhaAdesaoModel = new MinhaadesaoModel();
			$item->progresso = $MinhaAdesaoModel->getProgressoPreenchimentoAdesao($item->id, $item->co_edital);
		}

		return ['items' => $result, 'total' => $total];
	}

	/**
	 * Indefere uma adesão
	 *
	 * @return void
	 */
	public function indeferirAdesao()
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		// Verificar permissões
		if (!$user->authorise('respostas.manage', 'com_edital')) {
			echo json_encode([
				'success' => false,
				'message' => 'Você não tem permissão para indeferir adesões.'
			]);
			$app->close();
			return;
		}

		// Obter parâmetros
		$adesaoId = $this->input->getInt('adesao_id', 0);
		$motivo = trim($this->input->getString('motivo', ''));

		// Validar parâmetros
		if (!$adesaoId) {
			echo json_encode([
				'success' => false,
				'message' => 'ID da adesão não fornecido.'
			]);
			$app->close();
			return;
		}

		if (empty($motivo)) {
			echo json_encode([
				'success' => false,
				'message' => 'A justificativa do indeferimento é obrigatória.'
			]);
			$app->close();
			return;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');
			
			// Verificar se a adesão existe
			$query = $db->getQuery(true);
			$query->select('id, st_adesao, no_oficial')
				->from($db->quoteName('#__tb_adesao'))
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));
			
			$db->setQuery($query);
			$adesao = $db->loadObject();

			if (!$adesao) {
				echo json_encode([
					'success' => false,
					'message' => 'Adesão não encontrada.'
				]);
				$app->close();
				return;
			}

			// Verificar se já está indeferida
			if ($adesao->st_adesao === 'indeferido') {
				echo json_encode([
					'success' => false,
					'message' => 'Esta adesão já foi indeferida.'
				]);
				$app->close();
				return;
			}

			if ($motivoBloqueio = $this->getMotivoBloqueioFinalizacao($adesaoId)) {
				echo json_encode([
					'success' => false,
					'message' => $motivoBloqueio
				]);
				$app->close();
				return;
			}

			// Atualizar adesão para indeferido
			$query = $db->getQuery(true);
			
			$fields = [
				$db->quoteName('st_adesao') . ' = ' . $db->quote('indeferido'),
				$db->quoteName('ds_resposta') . ' = ' . $db->quote($motivo),
				$db->quoteName('modified_by') . ' = ' . $db->quote($user->id),
				$db->quoteName('dt_limite_situacao') . ' = ' . $db->quote(date('Y-m-d', strtotime('+30 days'))),
				$db->quoteName('dt_alterado') . ' = NOW()'
			];

			$query->update($db->quoteName('#__tb_adesao'))
				->set($fields)
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));

			$db->setQuery($query);
			$db->execute();

			// Inserir marco temporal
			$this->inserirMarcoTemporal($adesaoId, 'indeferido', $user->id);

			$this->enviarEmailEvento(
				$adesaoId,
				'adesao_indeferida.html',
				'Adesão Indeferida',
				[
					'{{STATUS}}' => 'Indeferido',
					'{{MOTIVO}}' => nl2br(htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8')),
				]
			);

			echo json_encode([
				'success' => true,
				'message' => $this->formatToastMessage(
					'Solicitação indeferida.',
					'Notificação de indeferimento enviada para ' . $adesao->no_oficial
				),
				'messageType' => 'error'
			]);
		} catch (\Exception $e) {
			echo json_encode([
				'success' => false,
				'message' => 'Erro ao indeferir adesão: ' . $e->getMessage()
			]);
		}

		$app->close();
	}

	/**
	 * Defere uma adesão
	 *
	 * @return void
	 */
	public function deferirAdesao()
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		// Verificar permissões
		if (!$user->authorise('respostas.manage', 'com_edital')) {
			echo json_encode([
				'success' => false,
				'message' => 'Você não tem permissão para deferir adesões.'
			]);
			$app->close();
			return;
		}

		// Obter parâmetros
		$adesaoId  = $this->input->getInt('adesao_id', 0);
		$modalidade = trim($this->input->getString('modalidade', ''));
		$observacoes = trim($this->input->getString('observacoes', ''));

		$modalidadesValidas = ['basica', 'intermediaria', 'plena'];

		// Validar parâmetros
		if (!$adesaoId) {
			echo json_encode([
				'success' => false,
				'message' => 'ID da adesão não fornecido.'
			]);
			$app->close();
			return;
		}

		if (!in_array($modalidade, $modalidadesValidas, true)) {
			echo json_encode([
				'success' => false,
				'message' => 'Modalidade de gestão inválida.'
			]);
			$app->close();
			return;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			// Verificar se a adesão existe
			$query = $db->getQuery(true);
			$query->select('id, st_adesao, no_oficial')
				->from($db->quoteName('#__tb_adesao'))
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));

			$db->setQuery($query);
			$adesao = $db->loadObject();

			if (!$adesao) {
				echo json_encode([
					'success' => false,
					'message' => 'Adesão não encontrada.'
				]);
				$app->close();
				return;
			}

			// Verificar se já está deferida
			if ($adesao->st_adesao === 'deferido') {
				echo json_encode([
					'success' => false,
					'message' => 'Esta adesão já foi deferida.'
				]);
				$app->close();
				return;
			}

			if ($motivoBloqueio = $this->getMotivoBloqueioFinalizacao($adesaoId)) {
				echo json_encode([
					'success' => false,
					'message' => $motivoBloqueio
				]);
				$app->close();
				return;
			}

			// Montar campos de atualização
			$fields = [
				$db->quoteName('st_adesao')    . ' = ' . $db->quote('deferido'),
				$db->quoteName('ds_modalidade') . ' = ' . $db->quote($modalidade),
				$db->quoteName('modified_by')  . ' = ' . $db->quote($user->id),
				$db->quoteName('dt_limite_situacao') . ' = ' . $db->quote(date('Y-m-d', strtotime('+30 days'))),
				$db->quoteName('dt_alterado')  . ' = NOW()'
			];

			if (!empty($observacoes)) {
				$fields[] = $db->quoteName('ds_resposta') . ' = ' . $db->quote($observacoes);
			}

			$query = $db->getQuery(true);
			$query->update($db->quoteName('#__tb_adesao'))
				->set($fields)
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));

			$db->setQuery($query);
			$db->execute();

			// Inserir marco temporal
			$this->inserirMarcoTemporal($adesaoId, 'deferido', $user->id);

			$modalidadeLabels = ['basica' => 'Básica', 'intermediaria' => 'Intermediária', 'plena' => 'Plena'];
			$conteudoObservacoes = !empty($observacoes)
				? '<h3>Observações</h3><div class="observacoes-box"><p>' . nl2br(htmlspecialchars($observacoes, ENT_QUOTES, 'UTF-8')) . '</p></div>'
				: '';
			$this->enviarEmailEvento(
				$adesaoId,
				'adesao_deferida.html',
				'Adesão Deferida',
				[
					'{{STATUS}}'               => 'Deferido',
					'{{MODALIDADE}}'           => $modalidadeLabels[$modalidade] ?? $modalidade,
					'{{CONTEUDO_OBSERVACOES}}' => $conteudoObservacoes,
				]
			);

			$this->trataParticularidadeConsorcio($adesaoId);

			echo json_encode([
				'success' => true,
				'message' => $this->formatToastMessage(
					'Solicitação deferida.',
					$adesao->no_oficial . ' foi aprovado com modalidade Gestão ' . $this->getModalidadeLabel($modalidade)
				),
				'messageType' => 'success'
			]);
		} catch (\Exception $e) {
			echo json_encode([
				'success' => false,
				'message' => 'Erro ao deferir adesão: ' . $e->getMessage()
			]);
		}

		$app->close();
	}

	/**
	 * Solicita complementação de uma adesão
	 *
	 * @return void
	 */
	public function solicitarComplementacao()
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		// Verificar permissões
		if (!$user->authorise('respostas.manage', 'com_edital')) {
			echo json_encode([
				'success' => false,
				'message' => 'Você não tem permissão para solicitar complementação.'
			]);
			$app->close();
			return;
		}

		// Obter parâmetros
		$adesaoId = $this->input->getInt('adesao_id', 0);
		$mensagem = trim($this->input->getString('mensagem', ''));

		// Validar parâmetros
		if (!$adesaoId) {
			echo json_encode([
				'success' => false,
				'message' => 'ID da adesão não fornecido.'
			]);
			$app->close();
			return;
		}

		if (empty($mensagem)) {
			echo json_encode([
				'success' => false,
				'message' => 'A mensagem de solicitação é obrigatória.'
			]);
			$app->close();
			return;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			// Verificar se a adesão existe
			$query = $db->getQuery(true);
			$query->select('id, st_adesao, no_oficial')
				->from($db->quoteName('#__tb_adesao'))
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));

			$db->setQuery($query);
			$adesao = $db->loadObject();

			if (!$adesao) {
				echo json_encode([
					'success' => false,
					'message' => 'Adesão não encontrada.'
				]);
				$app->close();
				return;
			}

			// Atualizar adesão para complementacao
			$fields = [
				$db->quoteName('st_adesao')   . ' = ' . $db->quote('complementacao'),
				$db->quoteName('ds_resposta') . ' = ' . $db->quote($mensagem),
				$db->quoteName('modified_by') . ' = ' . $db->quote($user->id),
				$db->quoteName('dt_limite_situacao') . ' = ' . $db->quote(date('Y-m-d', strtotime('+30 days'))),
				$db->quoteName('dt_alterado') . ' = NOW()'
			];

			$query = $db->getQuery(true);
			$query->update($db->quoteName('#__tb_adesao'))
				->set($fields)
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));

			$db->setQuery($query);
			$db->execute();

			// Inserir marco temporal
			$this->inserirMarcoTemporal($adesaoId, 'complementacao', $user->id);

			$this->enviarEmailEvento(
				$adesaoId,
				'complementacao_documento.html',
				'Solicitação de Complementação',
				[
					'{{STATUS}}'             => 'Complementação Solicitada',
					'{{MENSAGEM_AVALIADOR}}' => nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8')),
				]
			);

			echo json_encode([
				'success' => true,
				'message' => $this->formatToastMessage(
					'Solicitação de complementação enviada.',
					'Notificação enviada para ' . $adesao->no_oficial
				),
				'messageType' => 'info'
			]);
		} catch (\Exception $e) {
			echo json_encode([
				'success' => false,
				'message' => 'Erro ao solicitar complementação: ' . $e->getMessage()
			]);
		}

		$app->close();
	}

	private function getMotivoBloqueioFinalizacao(int $adesaoId): ?string
	{
		$adesaoModel = new AdesaoModel();
		$resumo = $adesaoModel->getResumoFinalizacao($adesaoId);

		if ($resumo->podeFinalizar) {
			return null;
		}

		return $resumo->motivoBloqueio ?: 'A solicitação só pode ser finalizada quando todos os documentos estiverem com status Aprovado ou Rejeitado.';
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
	protected function inserirMarcoTemporal($adesaoId, $marco, $userId = null)
	{

		// Se userId não fornecido, obter do usuário atual
		if ($userId === null) {
			$user = Factory::getApplication()->getIdentity();
			$userId = $user->id;
		}

		$adesaoModel = new AdesaoModel();
		return $adesaoModel->inserirMarcoTemporal($adesaoId, $marco, $userId);
	}

	/**
	 * Define apenas a modalidade de gestão de uma adesão, sem alterar o status.
	 *
	 * @return void
	 */
	public function definirModalidade()
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->authorise('respostas.manage', 'com_edital')) {
			echo json_encode([
				'success' => false,
				'message' => 'Você não tem permissão para definir a modalidade.'
			]);
			$app->close();
			return;
		}

		$adesaoId  = $this->input->getInt('adesao_id', 0);
		$modalidade = trim($this->input->getString('modalidade', ''));

		$modalidadesValidas = ['basica', 'intermediaria', 'plena'];

		if (!$adesaoId) {
			echo json_encode([
				'success' => false,
				'message' => 'ID da adesão não fornecido.'
			]);
			$app->close();
			return;
		}

		if (!in_array($modalidade, $modalidadesValidas, true)) {
			echo json_encode([
				'success' => false,
				'message' => 'Modalidade de gestão inválida.'
			]);
			$app->close();
			return;
		}

		try {
			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);

			$query->select('id, no_oficial')
				->from($db->quoteName('#__tb_adesao'))
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));

			$db->setQuery($query);
			$adesao = $db->loadObject();
			if (!$adesao) {
				echo json_encode([
					'success' => false,
					'message' => 'Adesão não encontrada.'
				]);
				$app->close();
				return;
			}

			$query = $db->getQuery(true);
			$query->update($db->quoteName('#__tb_adesao'))
				->set([
					$db->quoteName('ds_modalidade') . ' = ' . $db->quote($modalidade),
					$db->quoteName('modified_by')   . ' = ' . $db->quote($user->id),
					$db->quoteName('dt_alterado')   . ' = NOW()'
				])
				->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));

			$db->setQuery($query);
			$db->execute();

			echo json_encode([
				'success' => true,
				'message' => $this->formatToastMessage(
					'Modalidade definida.',
					$adesao->no_oficial . ': Gestão ' . $this->getModalidadeLabel($modalidade)
				),
				'messageType' => 'info'
			]);
		} catch (\Exception $e) {
			echo json_encode([
				'success' => false,
				'message' => 'Erro ao definir modalidade: ' . $e->getMessage()
			]);
		}

		$app->close();
	}

	/**
	 * Envia email de notificação de evento para o responsável da adesão.
	 *
	 * @param int    $adesaoId          ID da adesão
	 * @param string $templateName      Nome do arquivo de template (dentro de tmpl/email/)
	 * @param string $assunto           Assunto do email (sem o prefixo do site)
	 * @param array  $extraPlaceholders Placeholders extras específicos do evento
	 */
	public function enviarEmailEvento(int $adesaoId, string $templateName, string $assunto, array $extraPlaceholders = []): void
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
	private function formatToastMessage(string $title, string $detail): string
	{
		return '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong> '
			. htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
	}

	private function getModalidadeLabel(string $modalidade): string
	{
		$labels = [
			'basica' => 'Básica',
			'intermediaria' => 'Intermediária',
			'plena' => 'Plena',
		];

		return $labels[$modalidade] ?? $modalidade;
	}

	public function trataParticularidadeConsorcio(int $adesaoId): void
	{
		$adesaoModel = new AdesaoModel();
		$adesao = $adesaoModel->getItem($adesaoId);

		if ($adesao && $adesao->tp_esfera_governamental != 'consorcio'){
			return;
		}

		$municipioAdesaoModel = new \Comedital\Component\Edital\Site\Model\MunicipioAdesaoModel();
		$municipios = $municipioAdesaoModel->getMunicipiosByAdesao($adesaoId);


		foreach ($municipios as $municipio) {
			if (empty($municipio->ds_email)) {
				continue; // Pula se não houver email
			}
			
			try {
				$assunto = 'Adesão deferida - Envie as informações do município';
				$urlAdesaoComplementar = AdesaoHelper::getLinkAdesaoComplementar($adesaoId, $municipio->no_municipio, $municipio->co_ibge, $municipio->sg_estado);
				$extraPlaceholders = [
					'{{NOME_MUNICIPIO}}' => $municipio->no_municipio,
					'{{NOME_CONSORCIO}}' => $adesao->no_oficial,
					'{{NUMERO_PROTOCOLO}}' => AdesaoHelper::getProtocoloAdesao($adesaoId),
					'{{URL_ADESAO}}' => $urlAdesaoComplementar
				];
				$emailService = new AdesaoEmailService();
				$config = Factory::getConfig();
				$siteName = $config->get('sitename', 'SINAPIR');
				$html = $emailService->aplicarTemplate('adesao_complementar_municipio_consorcio.html', $extraPlaceholders, $siteName);
				EditalMailHelper::sendEmail(
					$municipio->ds_email,
					'[' . $siteName . '] ' . $assunto,
					$html
				);
			} catch (\Exception $e) {
				Factory::getApplication()->enqueueMessage(
					'Operação realizada com sucesso, mas houve problema no envio do email: ' . $e->getMessage(),
					'warning'
				);
			}
		}

	}
}
