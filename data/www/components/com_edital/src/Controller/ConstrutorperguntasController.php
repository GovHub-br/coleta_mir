<?php

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\CadastroHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

class ConstrutorperguntasController extends BaseController
{
	public function getModel($name = 'Construtorperguntas', $prefix = 'Site', $config = array())
	{
		return parent::getModel($name, $prefix, array('ignore_request' => true));
	}

	public function execute($task)
	{
		try
		{
			CadastroHelper::assertManage();

			if (!Factory::getApplication()->getIdentity()->authorise('respostas.manage', 'com_edital'))
			{
				throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
			}
		}
		catch (\Throwable $e)
		{
			$this->responder(false, $e->getMessage(), [], 403);
		}

		return parent::execute($task);
	}

	/**
	 * Recebe o POST `dados` (JSON com as seções) e grava a estrutura do formulário.
	 */
	public function salvar()
	{
		$app = Factory::getApplication();

		if ($app->input->getMethod() !== 'POST')
		{
			$this->responder(false, 'Método não permitido.', [], 405);
		}

		if (!Session::checkToken('post'))
		{
			$this->responder(false, Text::_('JINVALID_TOKEN_NOTICE'), [], 403);
		}

		$editalId = $app->input->post->getInt('edital_id', 0);
		$payload  = json_decode((string) $app->input->post->get('dados', '', 'raw'), true);

		if (!$editalId || !is_array($payload) || !isset($payload['secoes']) || !is_array($payload['secoes']))
		{
			$this->responder(false, 'Dados inválidos.', [], 400);
		}

		/** @var \Comedital\Component\Edital\Site\Model\ConstrutorperguntasModel $model */
		$model = $this->getModel();

		try
		{
			$estrutura = $model->salvarEstrutura($editalId, $payload['secoes']);
			$this->responder(true, 'Formulário salvo com sucesso.', ['secoes' => $estrutura]);
		}
		catch (\InvalidArgumentException $e)
		{
			$this->responder(false, $e->getMessage(), [], 422);
		}
		catch (\Throwable $e)
		{
			$this->responder(false, 'Não foi possível salvar o formulário.', [], 500);
		}
	}

	/**
	 * Recebe o POST `state` (1 publicado, 0 rascunho) e atualiza o formulário.
	 */
	public function publicar()
	{
		$app = Factory::getApplication();

		if ($app->input->getMethod() !== 'POST')
		{
			$this->responder(false, 'Método não permitido.', [], 405);
		}

		if (!Session::checkToken('post'))
		{
			$this->responder(false, Text::_('JINVALID_TOKEN_NOTICE'), [], 403);
		}

		$editalId = $app->input->post->getInt('edital_id', 0);
		$state    = $app->input->post->getInt('state', -1);

		if (!$editalId || !in_array($state, [0, 1], true))
		{
			$this->responder(false, 'Dados inválidos.', [], 400);
		}

		/** @var \Comedital\Component\Edital\Site\Model\ConstrutorperguntasModel $model */
		$model = $this->getModel();

		try
		{
			$model->alterarPublicacao($editalId, $state);
			$this->responder(true, $state === 1 ? 'Formulário publicado.' : 'Formulário voltou para rascunho.', ['state' => $state]);
		}
		catch (\InvalidArgumentException $e)
		{
			$this->responder(false, $e->getMessage(), [], 404);
		}
		catch (\Throwable $e)
		{
			$this->responder(false, 'Não foi possível alterar a publicação.', [], 500);
		}
	}

	private function responder(bool $sucesso, string $mensagem, array $dados, int $status = 200): void
	{
		/** @var \Joomla\CMS\Application\CMSApplication $app */
		$app = Factory::getApplication();

		$app->setHeader('status', (string) $status, true);
		$app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		$app->sendHeaders();

		echo new JsonResponse($dados, $mensagem, !$sucesso, true);

		$app->close();
	}
}
