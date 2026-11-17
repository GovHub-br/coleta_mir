<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * Perfis de preenchimento: cada formulário guarda em tb_edital.co_grupos os grupos de usuários
 * (ex.: "Coleta MIR - Gestor de TEDs") que podem iniciá-lo pelo botão "Novo instrumento" da
 * listagem. Super usuários veem todos os formulários com perfis definidos.
 *
 * @since  1.0.10
 */
class PerfilHelper
{
    /** Grupo pai dos perfis (Registered). */
    private const GRUPO_REGISTRADO = 2;

    /**
     * Grupos oferecidos como perfis no cadastro do formulário: filhos diretos de Registered sem
     * subgrupos (deixa de fora Author, que é a base de Editor e Publisher do Joomla).
     */
    public static function getGruposPerfil(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        return $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'title']))
                ->from($db->quoteName('#__usergroups'))
                ->where($db->quoteName('parent_id') . ' = ' . self::GRUPO_REGISTRADO)
                ->where($db->quoteName('rgt') . ' = ' . $db->quoteName('lft') . ' + 1')
                ->order($db->quoteName('lft'))
        )->loadObjectList();
    }

    /** Ids de grupos do formulário (co_grupos). */
    public static function getGruposFormulario($edital): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) ($edital->co_grupos ?? '')))));
    }

    /** O usuário pode iniciar o formulário pelo botão "Novo instrumento". */
    public static function podeIniciar(User $user, $edital): bool
    {
        $grupos = self::getGruposFormulario($edital);

        if (!$grupos || $user->guest) {
            return false;
        }

        return $user->authorise('core.admin') || (bool) array_intersect($grupos, $user->getAuthorisedGroups());
    }
}
