<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Form\Field\EditorField;

/**
 * Editor do cadastro no site: TinyMCE nativo, sem JCE.
 *
 * O JCE no frontend dispara GET em option=com_jce (idiomas) e o SEF
 * devolve HTML 403 — o editor não inicializa. Ao forçar TinyMCE aqui
 * sem importar o grupo inteiro de plugins (importPlugin('editors')),
 * apenas o TinyMCE é carregado e o JCE nunca injeta scripts na página.
 */
class CadastroeditorField extends EditorField
{
	public $type = 'Cadastroeditor';

	/**
	 * @return  Editor
	 */
	protected function getEditor()
	{
		if (empty($this->editor))
		{
			$this->editor = Editor::getInstance('tinymce');
		}

		return $this->editor;
	}
}
