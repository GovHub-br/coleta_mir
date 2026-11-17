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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Comedital\Component\Edital\Site\Helper\EditalHelper;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Comedital\Component\Edital\Site\Helper\AcessoHelper;
use Comedital\Component\Edital\Site\Helper\DwHelper;
use Comedital\Component\Edital\Site\Helper\EspelhoDwHelper;
use Comedital\Component\Edital\Site\Helper\OrigemHelper;
use Comedital\Component\Edital\Site\Model\RespostaModel;
use Comedital\Component\Edital\Site\Model\MunicipioAdesaoModel;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use \Joomla\CMS\User\UserHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;
use Comedital\Component\Edital\Site\Helper\EditalMailHelper;
use \Joomla\CMS\Log\Log;

/**
 * Adesao class.
 *
 * @since  1.0.0
 */
class AdesaoformController extends FormController
{
	/**
	 * Method to check out an item for editing and redirect to the edit form.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 *
	 * @throws  Exception
	 */
	public function edit($key = NULL, $urlVar = NULL)
	{
		// Get the previous edit id (if any) and the current edit id.
		$previousId = (int) $this->app->getUserState('com_edital.edit.adesao.id');
		$editId     = $this->input->getInt('id', 0);

		// Set the user id for the user to edit in the session.
		$this->app->setUserState('com_edital.edit.adesao.id', $editId);

		// Get the model.
		$model = $this->getModel('Adesaoform', 'Site');

		// Check out the item
		if ($editId) {
			$model->checkout($editId);
		}

		// Check in the previous user.
		if ($previousId) {
			$model->checkin($previousId);
		}

		// Redirect to the edit screen.
		$this->setRedirect(Route::_('index.php?option=com_edital&view=adesaoform&layout=edit', false));
	}


	/**
	 * Method to abort current operation
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function cancel($key = NULL)
	{

		// Get the current edit id.
		$editId = (int) $this->app->getUserState('com_edital.edit.adesao.id');

		// Get the model.
		$model = $this->getModel('Adesaoform', 'Site');

		// Check in the item
		if ($editId) {
			$model->checkin($editId);
		}

		$menu = Factory::getApplication()->getMenu();
		$item = $menu->getActive();
		$url  = (empty($item->link) ? 'index.php?option=com_edital&view=adesoes' : $item->link);
		$this->setRedirect(Route::_($url, false));
	}

	/**
	 * Method to remove data
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function remove()
	{
		$model = $this->getModel('Adesaoform', 'Site');
		$pk    = $this->input->getInt('id');

		// Attempt to save the data
		try {
			// Check in before delete
			$return = $model->checkin($return);
			// Clear id from the session.
			$this->app->setUserState('com_edital.edit.adesao.id', null);

			$menu = $this->app->getMenu();
			$item = $menu->getActive();
			$url = (empty($item->link) ? 'index.php?option=com_edital&view=adesoes' : $item->link);

			if ($return) {
				$model->delete($pk);
				$this->setMessage(Text::_('COM_EDITAL_ITEM_DELETED_SUCCESSFULLY'));
			} else {
				$this->setMessage(Text::_('COM_EDITAL_ITEM_DELETED_UNSUCCESSFULLY'), 'warning');
			}


			$this->setRedirect(Route::_($url, false));
			// Flush the data from the session.
			$this->app->setUserState('com_edital.edit.adesao.data', null);
		} catch (\Exception $e) {
			$errorType = ($e->getCode() == '404') ? 'error' : 'warning';
			$this->setMessage(Text::_('COM_EDITAL_ITEM_DELETED_UNSUCCESSFULLY'), $errorType); //evita mostrar erros de banco
			$this->setRedirect('index.php?option=com_edital&view=adesoes');
		}
	}

	/**
	 * Method to get municipalities by state via AJAX
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function getMunicipios()
	{
		// Check for request forgeries.
		$this->checkToken('get');

		$app = Factory::getApplication();
		$sgEstado = $app->input->getString('co_estado', '');

		if (!$sgEstado) {
			echo json_encode(['success' => false, 'message' => 'Estado não informado']);
			$app->close();
		}

		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select($db->quoteName(['m.co_ibge', 'm.no_municipio']))
			->from($db->quoteName('#__tb_municipio', 'm'))
			->join('INNER', $db->quoteName('#__tb_estado', 'e') . ' ON ' . $db->quoteName('m.co_estado') . ' = ' . $db->quoteName('e.co_estado'))
			->where($db->quoteName('e.sg_estado') . ' = ' . $db->quote($sgEstado))
			->order($db->quoteName('m.no_municipio') . ' ASC');

		$db->setQuery($query);

		try {
			$municipios = $db->loadObjectList();
			echo json_encode(['success' => true, 'data' => $municipios]);
		} catch (\Exception $e) {
			echo json_encode(['success' => false, 'message' => $e->getMessage()]);
		}

		$app->close();
	}

	/**
	 * Dados de um registro do espelho do data warehouse (TED, convênio, programa do PPA) para
	 * pré-preencher perguntas com campo_dw via AJAX.
	 *
	 * @return  void
	 */
	public function dadosDw()
	{
		// Check for request forgeries.
		$this->checkToken('get');

		$app = Factory::getApplication();
		$registro = $app->input->getCmd('registro', '');
		$chave = $app->input->getString('chave', '');

		// A resposta guarda só o código do instrumento; a fonte da pergunta diz o tipo.
		if ($registro === 'instrumento') {
			$chave = DwHelper::chaveInstrumento($app->input->getCmd('fonte', ''), $chave);
		}

		$dados = EspelhoDwHelper::getRegistro($registro, $chave);

		echo json_encode($dados === null ? ['success' => false] : ['success' => true, 'data' => $dados], JSON_UNESCAPED_UNICODE);
		$app->close();
	}

	/**
	 * Opções do espelho do data warehouse que dependem de outra resposta
	 * (ex.: metas do instrumento selecionado), para sugestões via AJAX.
	 *
	 * @return  void
	 */
	public function opcoesDw()
	{
		// Check for request forgeries.
		$this->checkToken('get');

		$app = Factory::getApplication();
		$fonte = $app->input->getCmd('fonte', '');
		// Filtro = código do instrumento escolhido na pergunta com a fonte "origem" (ex.: teds).
		$filtro = DwHelper::chaveInstrumento($app->input->getCmd('origem', ''), $app->input->getString('filtro', ''));

		if (!DwHelper::fonteComFiltro($fonte) || trim($filtro) === '') {
			echo json_encode(['success' => false]);
			$app->close();
		}

		$opcoes = array_map(static fn ($opcao) => ['nome' => $opcao['nome'], 'valor' => $opcao['valor']], EspelhoDwHelper::getOpcoes($fonte, $filtro));

		echo json_encode(['success' => true, 'data' => $opcoes], JSON_UNESCAPED_UNICODE);
		$app->close();
	}

	/**
	 * Function that allows child controller access to model data
	 * after the data has been saved.
	 *
	 * @param   BaseDatabaseModel  $model      The data model object.
	 * @param   array              $validData  The validated data.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function postSaveHook(BaseDatabaseModel $model, $validData = array()) {}

	public function saveComRespostas($key = NULL, $urlVar = NULL)
	{
		$app = Factory::getApplication();

		$contentLength = (int) $this->input->server->getInt('CONTENT_LENGTH', 0);
		if ($contentLength > 0 && $_POST === [] && $_FILES === []) {
			echo json_encode([
				'success' => false,
				'message' => 'O envio excedeu o tamanho máximo permitido. Reduza os arquivos anexados e tente novamente.',
			]);
			$app->close();

			return;
		}

		if ($app->getSession()->isNew()) {
			echo json_encode([
				'success' => false,
				'message' => Text::_('JLIB_ENVIRONMENT_SESSION_EXPIRED'),
			]);
			$app->close();

			return;
		}

		if (!Session::checkToken()) {
			echo json_encode([
				'success' => false,
				'message' => Text::_('JINVALID_TOKEN_NOTICE'),
			]);
			$app->close();

			return;
		}

		$db = Factory::getContainer()->get('DatabaseDriver');

		// Validate form data
		$resValidacao = $this->validarAdesao();

		if (!$resValidacao['success']) {
			echo json_encode($resValidacao);
			$app->close();
		}


		$dadosValidados = $resValidacao['data'];

		// TODO - rever validaçao de captcha pq vai operar com rascunho e usuario logado
		// talvez seja necessário apenas pra  criar o usuario
		// Validate captcha if enabled
		// $captchaValidation = $this->validateCaptcha();
		// if (!$captchaValidation['success']) {
		// 	echo json_encode($captchaValidation);
		// 	$app->close();
		// }

		$dadosEdital = EditalHelper::getEdital($dadosValidados['co_edital']);
		$userAtual = Factory::getUser();

		//consulta usuario por username
		$username = preg_replace('/[^0-9A-Za-z]/', '', $dadosValidados['ds_cpf']);
		$emailResponsavel = $dadosValidados['ds_email_responsavel'];
		$userByCpf     = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserByUsername($username);


		$user = Factory::getUser();
		if(!$user->guest && $user->id != ($userByCpf ? $userByCpf->id : 0)){
			echo json_encode([
				'success' => false,
				'message' => 'Você não tem permissão para enviar formulário para este CPF.'
			]);
			$app->close();
		}

		//verificar se email ja eh usado por algum usuario
		if(!empty($emailResponsavel)){
			$emailQuery = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__users'))
				->where($db->quoteName('email') . ' = :email')
				->bind(':email', $emailResponsavel);
			$db->setQuery($emailQuery);
			$emailUserId = (int) $db->loadResult();
			$userEmail = $emailUserId ? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($emailUserId) : null;
			

			if($userEmail && $userEmail->id != ($userByCpf ? $userByCpf->id : 0)){
				echo json_encode([
					'success' => false,
					'message' => 'O email do responsável já está associado a outro CNPJ. Por favor, utilize um email diferente.'
				]);
				$app->close();
			}
		}

		if ((!$userByCpf || $userByCpf->id == 0) && $user->guest) {
			//criar usuario
			$password = $dadosValidados['ds_senha'] !== '' ? $dadosValidados['ds_senha'] : $this->generateRandomPassword();

			$user = new User();
			$user->name           = $dadosValidados['no_responsavel'];
			$user->username       = $username;
			$user->email          = $emailResponsavel;
			//senha encriptada joomla
			$user->password		  = UserHelper::hashPassword($password);
			$user->groups         = [11]; // Grupo Ente

			if (!$user->save()) {
				throw new \Exception('Erro ao criar usuário: ' . $user->getError());
			}

			//logar usuario automaticamente
			$credentials = [
				'username' => $username,
				'password' => $password
			];
			$options = [
				'remember' => true,
				'silent' => true
			];
			$login = $app->login($credentials, $options);
			if(!$login) {
				//se isso acontecer tem que apagar o usuario pra fazer outroa adesao, senao fica usuario sem adesao
				$user->delete();
				throw new \Exception('Erro ao logar usuário: ' . $app->getError());
			}
		}

		// Start database transaction
		$db->transactionStart();

		// Disable autocommit to ensure full transaction control
		$db->setQuery('SET autocommit = 0');
		$db->execute();

		try {

			$data = $resValidacao['data'];

			if (($dadosEdital->tp_edital ?? '') === 'formulario')
			{
				$data['co_adesao_origem'] = (int) ($dadosValidados['co_adesao_origem'] ?? 0);
			}

			$data['state'] = 1; // Define o estado como publicado
			$adesaoExistente = !empty($data['id']) ? AdesaoHelper::getAdesaoById($data['id']) : null;
			if ($adesaoExistente && $adesaoExistente->st_adesao !== 'rascunho' && ($dadosEdital->tp_edital ?? '') === 'formulario') {
				// Atualização de resposta já enviada: salvar (inclusive como rascunho) não a reabre.
				$data['st_adesao'] = $adesaoExistente->st_adesao;
			} elseif($data['rascunho']){
				$data['st_adesao'] = 'rascunho'; // Define o estado como rascunho
			}else{
				$data['st_adesao'] = 'pendente'; // Define a situação como pendente
			}
			$data['created_by'] = $user->id; // Associa o usuário criado à adesão

			// Initialise variables.
			$model = $this->getModel('Adesaoform', 'Site');

			// Set the database connection to ensure same transaction
			if (method_exists($model, 'setDbo')) {
				$model->setDbo($db);
			}

			// Attempt to save the data.
			$adesaoId = $model->save($data);

			// Check for errors.
			if ($adesaoId === false) {
				// Rollback transaction on error
				$db->transactionRollback();

				echo json_encode([
					'success' => false,
					'message' => Text::sprintf('Save failed', $model->getError())
				]);
				$app->close();
			}

			// Save respostas - Pass the database connection to ensure transaction compliance
			if (!empty($data['respostas']) && !empty($adesaoId)) {
				foreach ($data['respostas'] as $perguntaId => $resposta) {

					// Pass the database connection to maintain transaction
					$result = PerguntaHelper::saveResposta($adesaoId, $perguntaId, $resposta, $db);

					// If any response fails to save, rollback the entire transaction
					if ($result === false) {
						$db->transactionRollback();

						echo json_encode([
							'success' => false,
							'message' => 'Erro ao salvar respostas. Operação cancelada.'
						]);
						$app->close();
					}
				}
			}

			// Commit transaction if everything succeeded
			$db->transactionCommit();

			// Re-enable autocommit
			$db->setQuery('SET autocommit = 1');
			$db->execute();

			// Clear the profile id from the session.
			$this->app->setUserState('com_edital.edit.adesao.id', null);
			$this->app->setUserState('com_edital.edit.adesao.data', null);

			//se nao for rascunho envia email de confirmacao
			// if($data['rascunho'] == 0 && $dadosEdital->tp_edital != 'formulario'){
			// 	// Enviar email de confirmação
			// 	$this->enviarEmailConfirmacao($adesaoId, $data, $password);
			// 	$this->enviarEmailNotificacaoGestor($adesaoId, $data);
			// }

			// Return success response
			$menu = Factory::getApplication()->getMenu();
			$item = $menu->getActive();

			$redirectUrl = ((int) ($data['rascunho'] ?? 0) === 0)
				? 'index.php?option=com_edital&view=formulariosrespostas&id=' . $adesaoId
				: 'index.php?option=com_edital&view=formularios';
			

			echo json_encode([
				'success' => true,
				'message' => Text::_('COM_EDITAL_ITEM_SAVED_SUCCESSFULLY'),
				'token' => Session::getFormToken(),
				'data' => [
					'id' => $adesaoId,
					'userId' => $user->id,
					'redirect' => Route::_($redirectUrl, false)
				]
			]);
		} catch (\Exception $e) {
			// Rollback transaction on any exception

			$db->transactionRollback();

			// Re-enable autocommit
			$db->setQuery('SET autocommit = 1');
			$db->execute();


			echo json_encode([
				'success' => false,
				'message' => $e->getMessage()
			]);
		}

		$app->close();
	}

	private function validarAdesao()
	{
		$dados = array();
		$dados['ds_cpf'] = preg_replace('/[^0-9]/', '', $this->input->getString('ds_cpf', '')); // Remove caracteres não numéricos
		$dados['ds_cnpj'] = preg_replace('/[^0-9]/', '', $this->input->getString('ds_cpf', '')); // Remove caracteres não numéricos
		$dados['no_responsavel'] = htmlspecialchars($this->input->getString('no_responsavel', ''), ENT_QUOTES, 'UTF-8')	;
		$dados['no_oficial'] = htmlspecialchars($this->input->getString('no_responsavel', ''), ENT_QUOTES, 'UTF-8');
		$dados['ds_email_responsavel'] = htmlspecialchars($this->input->getString('ds_email_responsavel', ''), ENT_QUOTES, 'UTF-8');
		$dados['ds_senha'] = $this->input->getString('ds_senha', '');
		$dados['ds_confirma_senha'] = $this->input->getString('ds_confirma_senha', '');
		$dados['co_edital'] = $this->input->getInt('co_edital', 0);
		$dados['id'] = $this->input->getInt('co_adesao', 0);
		$dados['ds_instituicao'] = htmlspecialchars($this->input->getString('ds_instituicao', ''), ENT_QUOTES, 'UTF-8');
		$dados['ds_cargo'] = htmlspecialchars($this->input->getString('ds_cargo', ''), ENT_QUOTES, 'UTF-8');
		
		$edital = EditalHelper::getEdital($dados['co_edital']);

		// Validação dos campos obrigatórios
		$errors = array();

		if($dados['id'] > 0){
			$adesao = AdesaoHelper::getAdesaoById($dados['id']);
			if(!$adesao){
				$errors[] = 'Adesão não encontrada';
			}else{
				$user = Factory::getUser();
				if($user->id != $adesao->created_by){
					$errors[] = 'Você não tem permissão para editar esta adesão.';
				} elseif ((int) $adesao->co_edital !== $dados['co_edital']) {
					$errors[] = 'Esta resposta pertence a outro formulário.';
				} elseif ($adesao->st_adesao !== 'rascunho' && ($edital->tp_edital ?? '') !== 'formulario') {
					// Respostas de formulário seguem editáveis depois de enviadas (atualizações periódicas).
					$errors[] = 'Apenas adesões em rascunho podem ser alteradas por este formulário.';
				}
			}
		}
		if(empty($dados['ds_cpf'])) {
			$errors[] = 'CPF é obrigatório';
		}


		if (empty($dados['ds_email_responsavel'])) {
			$errors[] = 'Email do responsável é obrigatório';
		}

		if( !empty($dados['ds_email_responsavel']) && !filter_var($dados['ds_email_responsavel'], FILTER_VALIDATE_EMAIL)) {
			$errors[] = 'Email do responsável inválido';
		}

		if (empty($dados['ds_instituicao'])) {
			$errors[] = 'Instituição é obrigatória';
		}
		
		if (empty($dados['ds_cargo'])) {
			$errors[] = 'Cargo é obrigatório';
		}

		// Validação de senha apenas quando o usuário ainda não está autenticado
		// (fluxo de criação de novo usuário do ente).
		if (Factory::getUser()->guest && $dados['ds_senha'] !== '') {
			$erroSenha = $this->validarForcaSenha($dados['ds_senha']);
			if ($erroSenha !== null) {
				$errors[] = $erroSenha;
			} elseif ($dados['ds_senha'] !== $dados['ds_confirma_senha']) {
				$errors[] = 'As senhas não coincidem.';
			}
		}

		if ($dados['co_edital'] <= 0) {
			$errors[] = 'Código do edital é obrigatório';
		}

		$resEditalPermiteAdesao = EditalHelper::editalPermiteAdesao($dados['co_edital']);
		if ($resEditalPermiteAdesao['success'] === false) {
			$errors[] = 'Edital não permite adesão. ' . $resEditalPermiteAdesao['message'];
		}

		$rascunho = $this->input->getInt('rascunho', 0) === 1;
		$dados['rascunho'] = $rascunho;

		$resPerguntas = PerguntaHelper::getPerguntasByEdital($dados['co_edital'], 'adesao');
		$inputPerguntas = $this->input->get('pergunta', [], 'array');
		$inputFiles     = $this->input->files->get('pergunta', null, 'raw');
		$respostasExistentes = [];

		if ($dados['id'] > 0) {
			$respostaModel = new RespostaModel();
			$respostasExistentes = $respostaModel->getRespostasMapByAdesao($dados['id']);
		}

		// Formulário de execução: as perguntas ligadas ao formulário de origem valem o que veio
		// da origem (resposta nova, pelo link do projeto) ou o que já foi gravado (edição).
		if (OrigemHelper::ehExecucao($edital)) {
			if ($dados['id'] > 0) {
				$valoresOrigem = OrigemHelper::getValoresSalvos($resPerguntas, $respostasExistentes);
				$dados['co_adesao_origem'] = (int) ($adesao->co_adesao_origem ?? 0);
			} else {
				$origem = OrigemHelper::resolver($edital, $this->input->getString('origem_key', ''), $this->input->getInt('projeto', -1));
				$valoresOrigem = $origem['valores'];
				$dados['co_adesao_origem'] = (int) ($origem['adesao']->id ?? 0);

				if ($origem['erro'] !== null) {
					$errors[] = $origem['erro'];
				}
			}

			foreach ($valoresOrigem as $perguntaId => $valor) {
				$inputPerguntas[$perguntaId] = $valor;
			}
		}

		// Formulário do gestor: instrumento e descrição vêm da UG/CNPJ liberada (resposta nova) ou do
		// que já foi gravado; o que vier no POST para essas perguntas é ignorado.
		if (AcessoHelper::getPerguntaAcessoUnidade($resPerguntas)) {
			$instrumento = AcessoHelper::valoresDoInstrumento($resPerguntas, $dados['id'] > 0 ? $respostasExistentes : [],
				$this->input->getString('instrumento', ''), Factory::getApplication()->getIdentity());

			if ($instrumento['erro'] !== null) {
				$errors[] = $instrumento['erro'];
			}

			foreach ($instrumento['valores'] as $perguntaId => $valor) {
				$inputPerguntas[$perguntaId] = $valor;
			}
		}

		$resValidacaoPerguntas = PerguntaHelper::validarRespostasPerguntas(
			$resPerguntas,
			$inputPerguntas,
			$inputFiles,
			$rascunho,
			$respostasExistentes
		);
		$errors = array_merge($errors, $resValidacaoPerguntas['errors']);
		$dados['respostas'] = $resValidacaoPerguntas['respostas'];

		$errors = array_merge($errors, PerguntaHelper::validarRespostasUnicas(
			$resPerguntas,
			$dados['respostas'],
			$dados['co_edital'],
			$dados['id']
		));

		// Se houver erros, retorna com mensagens
		if (!empty($errors)) {
			return array(
				'success' => false,
				'messages' => $errors
			);
		}

		return array(
			'success' => true,
			'data' => $dados
		);
	}


	/**
	 * Método para enviar email de confirmação após submissão bem-sucedida
	 *
	 * @param   int    $adesaoId  ID da adesão salva
	 * @param   array  $data      Dados da adesão
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function enviarEmailConfirmacao($adesaoId, $data, $senha)
	{
		try {
			$app = Factory::getApplication();
			$config = $app->getConfig();
			
			// Obter dados do edital
			$edital = EditalHelper::getEdital($data['co_edital']);
			if (!$edital) {
				throw new \Exception('Edital não encontrado');
			}

			// Preparar dados para o email
			$siteName = $config->get('sitename', 'SINAPIR');
			
			// Destinatários
			$emailResponsavel = $data['ds_email_responsavel'];
			$emailsAlternativos = !empty($data['ds_emails_alternativos']) ? 
				array_map('trim', explode(',', $data['ds_emails_alternativos'])) : [];
			
			// Assunto do email
			$assunto = '[' . $siteName . '] Confirmação da Solicitação de Adesão';
			
			if(!empty($senha)){
				$conteudoSenha = '<p>Para acessar o sistema de acompanhamento de adesões, você precisará fazer login utilizando o CNPJ informado e uma senha temporária. Abaixo estão os detalhes para acesso:</p>'; 
				$conteudoSenha .= '<table class="info-table">';
				$conteudoSenha .= '<tr>';
				$conteudoSenha .= '<td class="info-label">CNPJ para acesso:</td>';
				$conteudoSenha .= '<td>'.$data['ds_cnpj'].'</td>';
				$conteudoSenha .= '</tr>';
				// $conteudoSenha .= '<tr>';
				// $conteudoSenha .= '<td class="info-label">Senha temporária:</td>';
				// $conteudoSenha .= '<td>'.$senha.'</td>';
				// $conteudoSenha .= '</tr>';
				$conteudoSenha .= '</table>';
				// $conteudoSenha .= '<p>Por questões de segurança, será solicitado que você altere sua senha no primeiro acesso.</p>';
				
			}else{
				$conteudoSenha = '';
			}

			// Corpo do email
			$corpo = $this->montarCorpoEmail([
				'adesao_id' => $adesaoId,
				'nome_oficial' => $data['no_oficial'],
				'email_responsavel' => $emailResponsavel,
				'site_name' => $siteName,
				'conteudo_senha' => $conteudoSenha,
			]);
			
			$resultado = EditalMailHelper::sendEmail($emailResponsavel, $assunto, $corpo);

			if (!$resultado) {
				// Log do erro mas não interrompe o processo
				$app->enqueueMessage('Adesão salva com sucesso, mas houve problema no envio do email de confirmação.', 'warning');
			}

		} catch (\Exception $e) {
			// Log do erro mas não interrompe o processo principal
			$app = Factory::getApplication();
			$app->enqueueMessage('Adesão salva com sucesso, mas houve problema no envio do email de confirmação: ' . $e->getMessage(), 'warning');
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
	private function montarCorpoEmail($dados)
	{
		// Caminho do template de email
		$templatePath = JPATH_SITE . '/components/com_edital/tmpl/email/confirmacao_adesao.html';

		// $adesao = AdesaoHelper::getAdesaoById($dados['adesao_id']);
		// Get the model and item
		$model = $this->getModel('Adesao', 'Site');
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
		
		// Fazer as substituições dos placeholders
		$placeholders = [
			'{{NUMERO_PROTOCOLO}}' => $protocolo,
			'{{DATA_ENVIO}}' => $dataEnvio,
			'{{STATUS}}' => 'Aguardando análise',
			'{{NOME_OFICIAL}}' => $dados['nome_oficial'],
			'{{ESFERA}}' => $esfera,
			'{{LABEL_ITENS_ESFERA}}' => $esfera,
			'{{ITENS_ESFERA}}'          => $itensEsfera,
			'{{CONTEUDO_SENHA}}' => $dados['conteudo_senha'],
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
	 * Validate captcha if enabled
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function validateCaptcha()
	{
		// Check if captcha plugins are enabled
		$captchaPlugins = PluginHelper::getPlugin('captcha');
		
		if (empty($captchaPlugins)) {
			// No captcha plugins enabled, skip validation
			return ['success' => true];
		}

		// Get global configuration
		$config = Factory::getApplication()->getConfig();
		$captchaPlugin = $config->get('captcha', '0');
		
		// If no captcha is configured globally, skip validation
		if (!$captchaPlugin || $captchaPlugin === '0') {
			return ['success' => true];
		}

		try {
			// Get the captcha plugin
			$plugin = PluginHelper::getPlugin('captcha', $captchaPlugin);
			
			if (!$plugin) {
				return ['success' => true]; // Plugin not found, skip validation
			}

			// Import the captcha plugin
			PluginHelper::importPlugin('captcha', $captchaPlugin);
			
			// Trigger the captcha validation
			$app = Factory::getApplication();
			$captchaData = $this->input->get('jform_captcha');
			
			$captchaResponse = $app->triggerEvent('onCheckAnswer', [$captchaData]);

			// Check if any plugin returned false (validation failed)
			foreach ($captchaResponse as $response) {
				if ($response === false) {
					return [
						'success' => false,
						'messages' => ['Verificação de segurança (captcha) falhou. Tente novamente.']
					];
				}
			}

			return ['success' => true];
			
		} catch (\Exception $e) {
			return [
				'success' => false,
				'messages' => ['Erro na verificação de segurança: ' . $e->getMessage()]
			];
		}
	}

	public function consultaAdesaoCnpj(){
		// Check for request forgeries.
		$this->checkToken('get');

		$app = Factory::getApplication();
		$cnpj = $app->input->getString('cnpj', '');
		$idEdital = $app->input->getInt('id_edital', $app->input->getInt('idEdital', 0));

		if (!$cnpj) {
			echo json_encode(['success' => false, 'message' => 'CNPJ não informado']);
			$app->close();
		}

		$adesao = AdesaoHelper::getAdesaoByCnpj($cnpj, $idEdital);

		if ($adesao) {
			$usuarioLogado = Factory::getUser();
			if (!$usuarioLogado->guest && $usuarioLogado->id == $adesao->created_by && $adesao->st_adesao == 'rascunho') {
				echo json_encode(['success' => false, 'message' => 'Adesão deste CNPJ está em rascunho.']);
				$app->close();
			}

			echo json_encode(['success' => true, 'data' => AdesaoHelper::getProtocoloAdesao($adesao->id)]);
		} else {
			echo json_encode(['success' => false, 'message' => 'Nenhuma adesão encontrada para o CNPJ informado']);
		}

		$app->close();
	}


	/**
	 * Obtém o nome oficial a partir da UF ou do município selecionado.
	 * Usado quando o solicitante não é consórcio e o campo não é exibido no formulário.
	 *
	 * @param   array  $dados  Dados já coletados da requisição
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function resolverNomeOficialPorLocalidade(array $dados)
	{
		$esfera = $dados['tp_esfera_governamental'] ?? '';
		$db     = Factory::getContainer()->get('DatabaseDriver');

		if ($esfera === 'estadual' && !empty($dados['sg_estado'])) {
			$sgEstado = $dados['sg_estado'];
			$query = $db->getQuery(true)
				->select($db->quoteName('no_estado'))
				->from($db->quoteName('#__tb_estado'))
				->where($db->quoteName('sg_estado') . ' = :uf')
				->bind(':uf', $sgEstado);
			$db->setQuery($query);

			return trim((string) $db->loadResult());
		}

		if ($esfera === 'municipal' && !empty($dados['co_municipios']) && is_array($dados['co_municipios'])) {
			$coIbge = (int) reset($dados['co_municipios']);
			if ($coIbge <= 0) {
				return '';
			}

			$query = $db->getQuery(true)
				->select($db->quoteName('no_municipio'))
				->from($db->quoteName('#__tb_municipio'))
				->where($db->quoteName('co_ibge') . ' = :ibge')
				->bind(':ibge', $coIbge, \Joomla\Database\ParameterType::INTEGER);
			$db->setQuery($query);

			return trim((string) $db->loadResult());
		}

		return '';
	}

	/**
	 * Valida a força da senha informada pelo usuário.
	 *
	 * Regras: mínimo de 8 caracteres, ao menos 1 letra maiúscula, 1 letra
	 * minúscula, 1 número e 1 caractere especial.
	 *
	 * @param   string  $senha  Senha a ser validada
	 *
	 * @return  string|null  Mensagem de erro, ou null se a senha for válida
	 *
	 * @since   1.0.0
	 */
	private function validarForcaSenha($senha)
	{
		$requisitosFaltando = array();

		if (strlen($senha) < 8) {
			$requisitosFaltando[] = 'mínimo de 8 caracteres';
		}
		if (!preg_match('/[A-Z]/', $senha)) {
			$requisitosFaltando[] = '1 letra maiúscula';
		}
		if (!preg_match('/[a-z]/', $senha)) {
			$requisitosFaltando[] = '1 letra minúscula';
		}
		if (!preg_match('/\d/', $senha)) {
			$requisitosFaltando[] = '1 número';
		}
		if (!preg_match('/[^A-Za-z0-9]/', $senha)) {
			$requisitosFaltando[] = '1 caractere especial';
		}

		if (!empty($requisitosFaltando)) {
			return 'A senha deve conter: ' . implode(', ', $requisitosFaltando) . '.';
		}

		return null;
	}

	public function generateRandomPassword($length = 12) {
		return UserHelper::genRandomPassword($length);
	}

	public function enviarEmailNotificacaoGestor($adesaoId, $data)
	{
		try {
			$app = Factory::getApplication();
			$config = $app->getConfig();

			// Obter dados do edital
			$edital = EditalHelper::getEdital($data['co_edital']);
			if (!$edital) {
				throw new \Exception('Edital não encontrado');
			}

			// Preparar dados para o email
			$siteName = $config->get('sitename', 'SINAPIR');

			//busca usuarios gestores
			$emailsGestores = EditalHelper::getEmailsGestores();
			if (empty($emailsGestores)) {
				Log::add('Nenhum email de gestor encontrado para notificação.', Log::WARNING, 'com_edital');
				return false;
			}
			// Assunto do email
			$assunto = '[' . $siteName . '] Nova Solicitação de Adesão Recebida';

			// Corpo do email
			$corpo = EditalMailHelper::montarCorpoEmailGestao([
				'adesao_id' => $adesaoId,
				'nome_oficial' => $data['no_oficial'],
				'site_name' => $siteName
			]);

			foreach ($emailsGestores as $emailGestor) {
				$resultado = EditalMailHelper::sendEmail($emailGestor, $assunto, $corpo);

				if (!$resultado) {
					Log::add('Erro ao enviar email de notificação ao gestor: Falha no envio do email para ' . $emailGestor, Log::ERROR, 'com_edital');
				}
			}
		} catch (\Exception $e) {
			Log::add('Erro ao enviar email de notificação ao gestor: ' . $e->getMessage(), Log::ERROR, 'com_edital');
		}
	}

	
}
