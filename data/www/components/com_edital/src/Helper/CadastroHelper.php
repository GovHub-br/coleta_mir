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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * Rotas e autorização das telas de cadastro no site.
 *
 * ACL: Administrator\Helper\EditalHelper::getActions() + core.manage em com_edital.
 *
 * @since  1.0.0
 */
class CadastroHelper
{
	/**
	 * Exige a mesma permissão de entrada do componente no administrator.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 */
	public static function assertManage(): void
	{
		self::loadLanguages();

		$user = Factory::getApplication()->getIdentity();

		if (!$user || !$user->authorise('core.manage', 'com_edital'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	/**
	 * Carrega strings do site e da administração (rótulos dos XML reutilizados).
	 *
	 * @return  void
	 */
	public static function loadLanguages(): void
	{
		$lang = Factory::getApplication()->getLanguage();
		$lang->load('com_edital', JPATH_SITE);
		$lang->load('com_edital', JPATH_ADMINISTRATOR);
		$lang->load('joomla', JPATH_ADMINISTRATOR);

		$document = Factory::getApplication()->getDocument();

		if ($document)
		{
			$document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_edital');
		}
	}

	/**
	 * Itemid do menu de cadastro da view informada (lista, nunca adesaoform).
	 *
	 * @param   string  $view  View da lista (cadastroeditais, cadastroagrupamentos, cadastroperguntas)
	 *
	 * @return  int
	 */
	public static function getCadastroMenuId(string $view): int
	{
		static $cache = [];

		if (array_key_exists($view, $cache))
		{
			return $cache[$view];
		}

		$menus  = Factory::getApplication()->getMenu();
		$active = $menus->getActive();

		if ($active
			&& ($active->query['option'] ?? '') === 'com_edital'
			&& ($active->query['view'] ?? '') === $view)
		{
			return $cache[$view] = (int) $active->id;
		}

		foreach ($menus->getItems('component', 'com_edital') ?: [] as $item)
		{
			if (($item->query['view'] ?? '') === $view)
			{
				return $cache[$view] = (int) $item->id;
			}
		}

		return $cache[$view] = 0;
	}

	/**
	 * URL interna (index.php?...) da view, com Itemid do menu de lista.
	 *
	 * @param   string       $view      View de destino
	 * @param   array        $extra     Query extra
	 * @param   string|null  $menuView  View cujo menu fornece o Itemid (padrão: $view)
	 *
	 * @return  string
	 */
	public static function getViewUrl(string $view, array $extra = [], ?string $menuView = null): string
	{
		$query = array_merge(['option' => 'com_edital', 'view' => $view], $extra);
		$itemId = self::getCadastroMenuId($menuView ?? $view);

		if ($itemId)
		{
			$query['Itemid'] = $itemId;
		}

		return 'index.php?' . http_build_query($query);
	}

	/**
	 * URL interna do formulário de cadastro (novo ou edição).
	 *
	 * @param   string  $formView
	 * @param   string  $listView
	 * @param   int     $id
	 *
	 * @return  string
	 */
	public static function getFormUrl(string $formView, string $listView, int $id = 0): string
	{
		$extra = ['layout' => 'edit'];

		if ($id > 0)
		{
			$extra['id'] = $id;
		}

		return self::getViewUrl($formView, $extra, $listView);
	}

	/**
	 * Rota SEF da view de cadastro.
	 *
	 * @param   string       $view
	 * @param   array        $extra
	 * @param   string|null  $menuView
	 *
	 * @return  string
	 */
	public static function getViewRoute(string $view, array $extra = [], ?string $menuView = null): string
	{
		return Route::_(self::getViewUrl($view, $extra, $menuView), false);
	}

	/**
	 * Sufixo &Itemid= para redirects do FormController.
	 *
	 * @param   string  $listView
	 *
	 * @return  string
	 */
	public static function itemIdAppend(string $listView): string
	{
		$itemId = self::getCadastroMenuId($listView);

		return $itemId ? '&Itemid=' . $itemId : '';
	}
}
