<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

use \Joomla\CMS\Log\Log;
use \Joomla\Http\HttpFactory;
use \Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;

/**
 * Class EditalMailHelper
 *
 * Envia e-mails delegando o disparo para a API externa de e-mails
 * (equivalente a: curl -X POST "${URL_BASE_API}/api/emails/send"
 *  -H "Content-Type: application/json" -H "Authorization: Bearer ${API_TOKEN}" -d @payload.json)
 *
 * @since  1.0.0
 */
class EditalMailHelper
{
	/**
	 * Envia um e-mail através da API externa de e-mails.
	 *
	 * @param   string|array  $to           Destinatário(s) do e-mail.
	 * @param   string        $subject      Assunto do e-mail.
	 * @param   string        $body         Corpo do e-mail em HTML.
	 * @param   string|null   $from         E-mail do remetente.
	 * @param   string|null   $fromName     Nome do remetente (não utilizado pela API, mantido por compatibilidade).
	 * @param   array         $attachments  Lista de anexos no formato [['name' => ..., 'base64' => ...], ...].
	 *
	 * @return  bool  True em caso de sucesso (HTTP 2xx), false em caso de falha.
	 */
	public static function sendEmail($to, $subject, $body, $from = null, $fromName = null, array $attachments = [])
	{
		$urlBaseApi = getenv('SINAPIRMAIL_URL_BASE_API');
		$apiToken   = getenv('SINAPIRMAIL_API_TOKEN');
		$from	   = getenv('SINAPIRMAIL_FROM_ADDRESS');

		if (!$urlBaseApi || !$apiToken) {
			Log::add(
				'MailHelper: URL_BASE_API ou API_TOKEN não configurados nas variáveis de ambiente.',
				Log::ERROR,
				'com_edital'
			);

			return false;
		}

		$payload = [
			'from'        => $from ?: '',
			'to'          => \is_array($to) ? array_values($to) : [$to],
			'subject'     => $subject,
			'body'        => $body,
			'attachments' => $attachments,
		];

		$endpoint = rtrim($urlBaseApi, '/') . '/api/emails/send';

		try {
			$http = (new HttpFactory())->getHttp();

			$response = $http->post(
				$endpoint,
				json_encode($payload),
				[
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $apiToken,
				]
			);
			
			$statusCode = $response->getStatusCode();

			if ($statusCode < 200 || $statusCode >= 300) {
				Log::add(
					sprintf('MailHelper: falha ao enviar e-mail via API (HTTP %s): %s', $statusCode, (string) $response->getBody()),
					Log::ERROR,
					'com_edital'
				);

				return false;
			}

			return true;
		} catch (\Exception $e) {
			Log::add(
				sprintf('MailHelper: erro ao chamar API de e-mails: %s', $e->getMessage()),
				Log::ERROR,
				'com_edital'
			);

			return false;
		}
	}

	/**
	 * Monta o corpo do email de confirmação
	 *
	 * @param   array  $dados  Dados para montar o email
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function montarCorpoEmailGestao($dados, $templateName = 'gestao_adesao_recebida.html')
	{
		// Caminho do template de email
		$templatePath = JPATH_SITE . '/components/com_edital/tmpl/email/' . $templateName;

		// $adesao = AdesaoHelper::getAdesaoById($dados['adesao_id']);
		// Get the model and item
		$app = Factory::getApplication();
		$model = $app->bootComponent('com_edital')->getMVCFactory()->createModel('Adesao', 'Site');
		$adesao = $model->getItem($dados['adesao_id']);
		

		$protocolo = AdesaoHelper::getProtocoloAdesao($adesao->id);
		$dataEnvio = HTMLHelper::_('date', date('Y-m-d H:i:s'), 'd \d\e F \d\e Y, \à\s H:i');
		$esfera = $adesao->tp_esfera_governamental == 'municipal' ? 'Município' : ($adesao->tp_esfera_governamental == 'estadual' ? 'Estado' : 'Consórcios/Associações de Municípios');


		if($esfera != 'Estado'){
			$itensEsfera = $adesao->ds_municipios;
		}else{			
			$itensEsfera = $adesao->sg_estado;
		}

		// Verificar se o template existe
		if (!file_exists($templatePath)) {
			throw new \Exception('Template de email não encontrado: ' . $templatePath);
		}
		
		// Carregar o template
		$html = file_get_contents($templatePath);

		$linkBtn = substr(Uri::base(), 0, -1) . Route::_('index.php?option=com_edital&view=adesaorespostas&id=' . $dados['adesao_id']."&Itemid=".self::_getRespostasMenuId(), false);
		
		if($templateName == 'gestao_recurso_recebido.html'){
			$linkBtn .= '&layout=recurso'; 
		}


		// Fazer as substituições dos placeholders
		$placeholders = [
			'{{NUMERO_PROTOCOLO}}' => $protocolo,
			'{{DATA_ENVIO}}' => $dataEnvio,
			'{{STATUS}}' => 'Aguardando análise',
			'{{NOME_OFICIAL}}' => $dados['nome_oficial'],
			'{{ESFERA}}' => $esfera,
			'{{LABEL_ITENS_ESFERA}}' => $esfera,
			'{{ITENS_ESFERA}}'          => $itensEsfera,
			'{{URL_ACESSO_GESTOR}}' => $linkBtn,
		];

		// Substituir todos os placeholders
		foreach ($placeholders as $placeholder => $valor) {
			$html = str_replace($placeholder, $valor, $html);
		}

		$html = LayoutHelper::render('joomla.mail.mailtemplate', [
			'mail'  => $html,
			'extra' => [
				'logoUrl'  => Uri::root() . 'images/logo/logo_comprovante.png',
				'siteName' => $dados['site_name'],
				'lang'     => 'pt-BR',
			],
		]);

		return $html;
	}

	/**
	 * Get the adesaogestao menu Itemid
	 *
	 * @return int|null
	 *
	 * @since  1.0.0
	 */
	protected static function _getRespostasMenuId()
	{
		$app = Factory::getApplication();
		$menus = $app->getMenu('site');
		
		$menuItems = $menus->getItems('component', 'com_edital');
		foreach ($menuItems as $item) {
			if (isset($item->query['view']) && $item->query['view'] === 'adesaorespostas') {
				return $item->id;
			}
		}
		
		return null;
	}
}
