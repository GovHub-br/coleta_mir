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

use \Joomla\CMS\Factory;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Class EditalFrontendHelper
 *
 * @since  1.0.0
 */
class EditalHelper
{
	/**
	 * Id do item de menu "Meus Formulários" (view=formularios).
	 * Com roteamento estrito, os links do preenchimento de formulários precisam informá-lo
	 * para a página ter título e breadcrumb.
	 *
	 * @return  int|null
	 */
	public static function getItemidMeusFormularios()
	{
		$item = Factory::getApplication()->getMenu()->getItems('link', 'index.php?option=com_edital&view=formularios', true);

		return $item ? (int) $item->id : null;
	}

	/**
	 * Insere "Minha Adesão" no início do breadcrumb, antes de "Meus Formulários",
	 * que fica na raiz do menu.
	 *
	 * @return  void
	 */
	public static function addMinhaAdesaoPathway()
	{
		$app     = Factory::getApplication();
		$pathway = $app->getPathway();
		$items   = $pathway->getPathway();

		$minhaAdesaoLink = 'index.php?option=com_edital&view=minhaadesao';
		$minhaAdesao     = $app->getMenu()->getItems('link', $minhaAdesaoLink, true);

		foreach ($items as $item)
		{
			if (strpos((string) $item->link, 'view=minhaadesao') !== false)
			{
				return;
			}
		}

		$link = $minhaAdesao ? 'index.php?Itemid=' . $minhaAdesao->id : $minhaAdesaoLink;
		$name = $minhaAdesao ? $minhaAdesao->title : 'Minha Adesão';

		array_unshift($items, (object) ['name' => $name, 'link' => $link]);
		$pathway->setPathway($items);
	}

	/**
	 * Gets the files attached to an item
	 *
	 * @param   int     $pk     The item's id
	 *
	 * @param   string  $table  The table's name
	 *
	 * @param   string  $field  The field's name
	 *
	 * @return  array  The files
	 */
	public static function getFiles($pk, $table, $field)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$query
			->select($field)
			->from($table)
			->where('id = ' . (int) $pk);

		$db->setQuery($query);

		return explode(',', $db->loadResult());
	}

	/**
	 * Gets an edital by ID
	 *
	 * @param   int  $id  The edital ID
	 *
	 * @return  mixed  The edital object or null
	 */
	public static function getEdital($id)
	{
		$model = Factory::getApplication()
			->bootComponent('com_edital')
			->getMVCFactory()
			->createModel('Edital', 'Site', ['ignore_request' => true]);
		
		return $model->getItem($id);
	}

	public static function editalPermiteAdesao($idEdital)
	{
		$model = Factory::getApplication()
			->bootComponent('com_edital')
			->getMVCFactory()
			->createModel('Edital', 'Site', ['ignore_request' => true]);

		$edital = $model->getItem($idEdital);

		try{

			if(!$edital){
				throw new \Exception('Edital não encontrado.');
			}

			$now = new \DateTime();

			$dataInicio = new \DateTime($edital->dt_abertura);
			$dataFim = new \DateTime($edital->dt_encerramento);
			$dataPublicacao = new \DateTime($edital->dt_publicacao);

			if($now < $dataInicio){
				throw new \Exception('O período de adesão ainda não começou.');
			}

			if($now > $dataFim){
				throw new \Exception('O período de adesão já terminou.');
			}

			if($now < $dataPublicacao){
				throw new \Exception('Edital ainda não foi publicado.');
			}

			if ($edital->state != 1){
				throw new \Exception('Edital não está publicado.');
			}

			$result = [
				'success' => true,
				'message' => 'Edital permite adesão.'
			];

		}catch(\Exception $e){
			$result = [
				'success' => false,
				'message' => $e->getMessage()
			];

		}
		

		
		return $result;
	}

	/**
	 * Empty modality means no lock; otherwise it must match the solicitante.
	 */
	public static function usuarioAtendeEsferaSolicitante($edital, $esferaUsuario): bool
	{
		$esferaEdital = is_object($edital) ? (string) ($edital->tp_esfera_solicitante ?? '') : '';

		if ($esferaEdital === '')
		{
			return true;
		}

		return $esferaEdital === (string) $esferaUsuario;
	}

	/**
	 * Gets municipalities by state code
	 *
	 * @param   int  $coEstado  The state code
	 *
	 * @return  array  Array of municipalities
	 */
	public static function getMunicipiosByEstado($coEstado)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select($db->quoteName(['co_ibge', 'no_municipio']))
			->from($db->quoteName('#__tb_municipio'))
			->where($db->quoteName('co_estado') . ' = ' . $db->quote($coEstado))
			->order($db->quoteName('no_municipio') . ' ASC');

		$db->setQuery($query);

		try {
			return $db->loadObjectList();
		} catch (\Exception $e) {
			return [];
		}
	}

	/**
	 * Gets municipalities by state code
	 *
	 * @param   int  $coEstado  The state code
	 *
	 * @return  object Municipality object
	 */
	public static function getMunicipio($id)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select($db->quoteName(['co_ibge', 'no_municipio']))
			->from($db->quoteName('#__tb_municipio'))
			->where($db->quoteName('co_ibge') . ' = ' . $db->quote($id))
			->order($db->quoteName('no_municipio') . ' ASC');

		$db->setQuery($query);

		try {
			return $db->loadObject();
		} catch (\Exception $e) {
			return [];
		}
	}

	/**
	 * Gets the edit permission for an user
	 *
	 * @param   mixed  $item  The item
	 *
	 * @return  bool
	 */
	public static function canUserEdit($item)
	{
		$permission = false;
		$user       = Factory::getApplication()->getIdentity();

		if ($user->authorise('core.edit', 'com_edital') || (isset($item->created_by) && $user->authorise('core.edit.own', 'com_edital') && $item->created_by == $user->id) || $user->authorise('core.create', 'com_edital'))
		{
			$permission = true;
		}

		return $permission;
	}

	public static function getEmailsGestores()
	{
		$groupId = 10; // ID do grupo de gestores
		$userIds = \Joomla\CMS\Access\Access::getUsersByGroup($groupId);

		$emails = [];
		foreach ($userIds as $userId) {
			$user = \Joomla\CMS\Factory::getUser($userId);
			if (!empty($user->email) && $user->block == 0 && $user->sendEmail == 1) { // Verifica se o usuário não está bloqueado
				$emails[] = $user->email;
			}
		}

		return $emails;
	}
}
