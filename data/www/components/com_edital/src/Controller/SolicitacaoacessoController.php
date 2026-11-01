<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AcessoHelper;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

/**
 * Acesso ao Coleta MIR: cadastro com pedido de perfis, pedidos de quem já tem conta, nome da
 * unidade (UG/CNPJ) para o formulário e decisão do MIR sobre os pedidos.
 *
 * @since  1.0.11
 */
class SolicitacaoacessoController extends BaseController
{
	private function voltar(string $view = 'solicitacaoacesso'): void
	{
		$this->setRedirect(Route::_('index.php?option=com_edital&view=' . $view, false));
	}

	/** Pedidos de perfil marcados no formulário (perfis[] e unidade_<perfil>). Devolve as mensagens de erro. */
	private function registrarPedidos(int $userId): array
	{
		$erros = [];
		$justificativa = $this->input->post->getString('justificativa', '');

		foreach (array_unique((array) $this->input->post->get('perfis', [], 'array')) as $perfil)
		{
			$perfil = (string) $perfil;

			if ($perfil === 'mir' && AcessoHelper::ehMir(Factory::getUser($userId)))
			{
				continue;
			}

			$erro = AcessoHelper::solicitar($userId, $perfil, $this->input->post->getString('unidade_' . $perfil, ''), $justificativa);

			if ($erro !== null)
			{
				$erros[] = (AcessoHelper::PERFIS[$perfil]['rotulo'] ?? $perfil) . ': ' . $erro;
			}
		}

		return $erros;
	}

	/** Cria a conta (sem login) e registra os pedidos de perfil. */
	public function cadastrar()
	{
		$this->checkToken();

		$app = $this->app;

		if (!$app->getIdentity()->guest)
		{
			$this->voltar();

			return;
		}

		$dados = [
			'nome'          => trim($this->input->post->getString('nome', '')),
			'cpf'           => trim($this->input->post->getString('cpf', '')),
			'email'         => trim($this->input->post->getString('email', '')),
			'justificativa' => $this->input->post->getString('justificativa', ''),
			'perfis'        => (array) $this->input->post->get('perfis', [], 'array'),
		];

		foreach (array_keys(AcessoHelper::PERFIS) as $perfil)
		{
			$dados['unidade_' . $perfil] = $this->input->post->getString('unidade_' . $perfil, '');
		}

		$senha = $this->input->post->getRaw('senha', '');
		$cpf = PerguntaHelper::cpfValido($dados['cpf']);
		$erros = [];

		if ($dados['nome'] === '')
		{
			$erros[] = 'Informe o nome completo.';
		}

		if ($cpf === '')
		{
			$erros[] = 'Informe um CPF válido.';
		}
		elseif (UserHelper::getUserId($cpf))
		{
			$erros[] = 'Já existe uma conta com este CPF. Entre com ele ou use "Esqueceu sua senha?".';
		}

		if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL))
		{
			$erros[] = 'Informe um e-mail válido.';
		}
		else
		{
			$db = Factory::getContainer()->get('DatabaseDriver');
			$emailEmUso = $db->setQuery(
				$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__users'))
					->where('LOWER(' . $db->quoteName('email') . ') = ' . $db->quote(mb_strtolower($dados['email'])))
			)->loadResult();

			if ($emailEmUso)
			{
				$erros[] = 'Este e-mail já está em outra conta.';
			}
		}

		if (mb_strlen($senha) < 8)
		{
			$erros[] = 'A senha precisa ter pelo menos 8 caracteres.';
		}
		elseif ($senha !== $this->input->post->getRaw('senha2', ''))
		{
			$erros[] = 'As senhas não conferem.';
		}

		if ($erros)
		{
			$app->setUserState('com_edital.solicitacaoacesso.dados', $dados);
			$app->enqueueMessage(implode('<br>', $erros), 'error');
			$this->voltar();

			return;
		}

		$user = new User();
		$user->name = $dados['nome'];
		$user->username = $cpf;
		$user->email = $dados['email'];
		$user->password = UserHelper::hashPassword($senha);
		$user->groups = array_values(array_filter([2, AcessoHelper::getGrupoColetaId()]));

		if (!$user->save())
		{
			$app->setUserState('com_edital.solicitacaoacesso.dados', $dados);
			$app->enqueueMessage('Não foi possível criar a conta: ' . $user->getError(), 'error');
			$this->voltar();

			return;
		}

		$app->login(['username' => $cpf, 'password' => $senha], ['silent' => true]);

		$erros = $this->registrarPedidos((int) $user->id);
		$app->enqueueMessage('Conta criada. ' . ($dados['perfis'] ? 'Os perfis pedidos aguardam a liberação do MIR.' : ''), 'success');

		if ($erros)
		{
			$app->enqueueMessage(implode('<br>', $erros), 'warning');
		}

		$this->voltar();
	}

	/** Pedido de perfil de quem já tem conta. */
	public function solicitar()
	{
		$this->checkToken();

		$user = $this->app->getIdentity();

		if ($user->guest)
		{
			$this->voltar();

			return;
		}

		if (!$this->input->post->get('perfis', [], 'array'))
		{
			$this->app->enqueueMessage('Marque o perfil que você precisa.', 'warning');
		}
		elseif ($erros = $this->registrarPedidos((int) $user->id))
		{
			$this->app->enqueueMessage(implode('<br>', $erros), 'error');
		}
		else
		{
			$this->app->enqueueMessage('Pedido registrado. Ele aguarda a liberação do MIR.', 'success');
		}

		$this->voltar();
	}

	/** Nome da unidade (UG/CNPJ) nos instrumentos do DW, para conferência no formulário. */
	public function unidade()
	{
		$this->checkToken('get');

		$tipo = $this->input->getCmd('tipo', '');
		$codigo = AcessoHelper::normalizarIdentificador($tipo, $this->input->getString('codigo', ''));
		$unidade = $codigo !== '' ? AcessoHelper::getUnidade($tipo, $codigo) : null;

		echo json_encode($unidade
			? ['success' => true, 'data' => $unidade]
			: ['success' => false, 'message' => $codigo === ''
				? ($tipo === 'ug' ? 'UG inválida.' : 'CNPJ inválido.')
				: 'Nenhum instrumento com esta ' . ($tipo === 'ug' ? 'UG' : 'CNPJ') . ' na base do MIR. O pedido pode ser feito, mas confira o número.'],
			JSON_UNESCAPED_UNICODE);
		$this->app->close();
	}

	/** Aprovação ou recusa de um pedido, pelo MIR. */
	public function decidir()
	{
		$this->checkToken();

		$user = $this->app->getIdentity();

		if (!AcessoHelper::ehMir($user))
		{
			throw new \Exception('Sem permissão.', 403);
		}

		$aprovar = $this->input->post->getCmd('decisao', '') === 'aprovar';
		$erro = AcessoHelper::decidir($this->input->post->getInt('id'), $aprovar, $this->input->post->getString('motivo', ''), (int) $user->id);

		$this->app->enqueueMessage($erro ?? ($aprovar ? 'Acesso liberado.' : 'Pedido recusado.'), $erro ? 'error' : 'success');
		$this->voltar('solicitacoesacesso');
	}
}
