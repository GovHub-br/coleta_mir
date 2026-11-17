<?php

namespace Sinapir\Plugin\System\SinapirLogin\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Mail\BeforeRenderingMailTemplateEvent;
use Joomla\CMS\Event\User\AfterLoginEvent;
use Joomla\CMS\Event\User\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

/**
 * Plugin de redirecionamento pós-login baseado no perfil do usuário.
 *
 * Grupos:
 *  - MIR / Gerenciar Adesões (id: 10) → Painel de Gestão
 *  - Ente (id: 11) → Minha Adesão
 *  - Ambos → Tela de escolha de perfil
 *
 * Também simplifica a recuperação de senha: o link do e-mail leva o id do usuário, e a
 * etapa que pede nome de usuário + código de verificação é pulada.
 */
final class SinapirLogin extends CMSPlugin implements SubscriberInterface
{
    // ID do grupo MIR — deve corresponder ao banco de dados
    private const GROUP_MIR = 10;

    // Alias dos itens de menu — estáveis entre ambientes (não usar Itemid fixo)
    private const ROUTE_PAINEL = 'gestao-de-formularios';
    private const ROUTE_ADESAO = 'formularios';
    private const ROUTE_ESCOLHA = 'escolha-de-perfil';

    // Destino que o com_users usa quando o formulário de login não informa "return"
    private const LOGIN_DEFAULT_RETURN = 'index.php?option=com_users&view=profile';

    /**
     * Resolve o alias do menu para URL interna com Itemid do ambiente atual.
     */
    private function resolveMenuRouteByAlias(string $alias): ?string
    {
        $item = $this->getApplication()->getMenu('site')->getItems(['alias'], [$alias], true);

        if (!$item) {
            return null;
        }

        return 'index.php?Itemid=' . (int) $item->id;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterRoute' => 'onAfterRoute',
            'onMailBeforeRendering' => 'onMailBeforeRendering',
            'onUserAfterLogin' => 'onUserAfterLogin',
            'onUserAfterSave' => 'onUserAfterSave',
        ];
    }

    /**
     * O destino é trocado no user state que o UserController do com_users lê depois do
     * login (users.login.form.return), em vez de redirecionar daqui: um redirect neste
     * evento quebrava a resposta JSON do envio da adesão, que loga o usuário novo por AJAX.
     */
    public function onUserAfterLogin(AfterLoginEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        // Só substitui o destino padrão (perfil); um "return" explícito no formulário é respeitado
        if ($app->getUserState('users.login.form.return') !== self::LOGIN_DEFAULT_RETURN) {
            return;
        }

        $subject = $event->getArgument('subject');
        $username = \is_object($subject) ? ($subject->username ?? null) : ($subject['username'] ?? null);

        if (!$username) {
            return;
        }

        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserByUsername($username);

        if (!$user || !$user->id) {
            return;
        }

        // Se o usuário precisa trocar a senha, vai para a tela de edição de perfil
        if (!empty($user->requireReset)) {
            $app->getSession()->set('sinapir.forcedPasswordReset', true);
            $app->setUserState('users.login.form.return', 'index.php?option=com_users&view=profile&layout=edit');
            return;
        }

        $groups = $user->getAuthorisedGroups();

        $isMir = \in_array(self::GROUP_MIR, $groups, true);

        if ($isMir) {
            $alias = self::ROUTE_PAINEL;
        } else {
            // Demais usuários (Ente, perfis do Coleta MIR, responsáveis de projeto vinculados pelo CPF):
            // a listagem dos instrumentos.
            $alias = self::ROUTE_ADESAO;
        }

        $redirect = $this->resolveMenuRouteByAlias($alias);

        if (!$redirect) {
            return;
        }

        $app->setUserState('users.login.form.return', $redirect);
    }

    /**
     * Acrescenta o id do usuário ao link do e-mail de recuperação de senha, para que a
     * etapa de confirmação não precise pedir o nome de usuário (ver skipResetConfirmStep).
     */
    public function onMailBeforeRendering(BeforeRenderingMailTemplateEvent $event): void
    {
        if ($event->getTemplateId() !== 'com_users.password_reset') {
            return;
        }

        $mailTemplate = $event->getTemplate();
        $data         = $mailTemplate->getTemplateData();

        if (empty($data['id']) || empty($data['token'])) {
            return;
        }

        // Mesmo link montado em ResetModel::processResetRequest(), com o uid a mais
        $mode = $this->getApplication()->get('force_ssl', 0) == 2 ? Route::TLS_FORCE : Route::TLS_IGNORE;
        $link = 'index.php?option=com_users&view=reset&layout=confirm&token=' . $data['token'] . '&uid=' . (int) $data['id'];

        $mailTemplate->addTemplateData([
            'link_text' => Route::link('site', $link, false, $mode, true),
            'link_html' => Route::link('site', $link, true, $mode, true),
        ]);
    }

    public function onAfterRoute(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site') || $app->getInput()->getCmd('option') !== 'com_users') {
            return;
        }

        $this->normalizeCpfUsername();
        $this->skipResetConfirmStep();
    }

    /**
     * O usuário do Ente é o CPF só com dígitos. O modal de login já tira a pontuação, mas a
     * página de login do com_users e o formulário de confirmação da recuperação de senha
     * não; aqui o CPF digitado com máscara é aceito nos dois.
     */
    private function normalizeCpfUsername(): void
    {
        $input = $this->getApplication()->getInput();
        $task  = $input->getCmd('task');
        $cpf   = '/^\d{3}\.\d{3}\.\d{3}-\d{2}$/';

        if ($task === 'user.login') {
            $username = trim($input->post->getString('username'));

            if (preg_match($cpf, $username)) {
                $input->post->set('username', preg_replace('/\D/', '', $username));
            }
        } elseif ($task === 'reset.confirm') {
            $jform = $input->get('jform', [], 'array');

            if (isset($jform['username']) && preg_match($cpf, trim($jform['username']))) {
                $jform['username'] = preg_replace('/\D/', '', $jform['username']);
                $input->set('jform', $jform);
            }
        }
    }

    /**
     * Quando o link de recuperação traz uid e token válidos, faz o que o
     * ResetModel::processResetConfirm() faria e segue direto para a tela de nova senha.
     * O formulário padrão (CPF + código) continua valendo para links sem uid, como os
     * enviados antes desta mudança.
     */
    private function skipResetConfirmStep(): void
    {
        $app   = $this->getApplication();
        $input = $app->getInput();

        if ($input->getMethod() !== 'GET' || $input->getCmd('view') !== 'reset' || $input->getCmd('layout') !== 'confirm') {
            return;
        }

        $userId = $input->getInt('uid');
        $token  = $input->getAlnum('token');

        if (!$userId || $token === '') {
            return;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'activation', 'block']))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $userId, ParameterType::INTEGER);

        $user = $db->setQuery($query)->loadObject();

        // Mesmas verificações do core; o código some da conta assim que a senha é trocada
        if (!$user || !$user->activation || $user->block || !UserHelper::verifyPassword($token, $user->activation)) {
            $app->enqueueMessage('Este link de recuperação de senha é inválido ou já foi utilizado. Informe seu e-mail para receber um novo link.', 'warning');
            $app->redirect(Route::_('index.php?option=com_users&view=reset', false));
        }

        $app->setUserState('com_users.reset.token', $user->activation);
        $app->setUserState('com_users.reset.user', (int) $user->id);

        $app->redirect(Route::_('index.php?option=com_users&view=reset&layout=complete', false));
    }

    public function onUserAfterSave(AfterSaveEvent $event): void
    {
        // #TODO - esta bugando o salvar rascunho resolver oq fazer depois
        return;

        if ($event->getIsNew() || !$event->getSavingResult()) {
            return;
        }

        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $session = $app->getSession();

        // Redireciona apenas quando for a troca de senha forçada
        if ($session->get('sinapir.forcedPasswordReset', false)) {
            $session->remove('sinapir.forcedPasswordReset');

            $redirect = $this->resolveMenuRouteByAlias(self::ROUTE_ADESAO);

            if ($redirect) {
                $app->redirect(Route::_($redirect, false));
            }
        }
    }
}
