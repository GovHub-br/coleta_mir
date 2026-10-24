<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\CadastroHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$canDo       = $displayData['canDo'];
$list        = $displayData['listController'];
$form        = $displayData['formController'];
$filterState = (string) ($displayData['filterState'] ?? '');
$newUrl      = Route::_(CadastroHelper::getFormUrl($form, $list));
?>
<div class="com-edital-cadastro__toolbar d-flex flex-wrap gap-2 mb-3">
	<?php if ($canDo->get('core.create')) : ?>
		<a href="<?php echo $newUrl; ?>" class="btn btn-primary">
			<?php echo Text::_('JTOOLBAR_NEW'); ?>
		</a>
	<?php endif; ?>

	<?php if ($canDo->get('core.edit.state')) : ?>
		<button type="button" class="btn btn-outline-success js-cadastro-task" data-task="<?php echo htmlspecialchars($list, ENT_QUOTES, 'UTF-8'); ?>.publish">
			<?php echo Text::_('JTOOLBAR_PUBLISH'); ?>
		</button>
		<button type="button" class="btn btn-outline-secondary js-cadastro-task" data-task="<?php echo htmlspecialchars($list, ENT_QUOTES, 'UTF-8'); ?>.unpublish">
			<?php echo Text::_('JTOOLBAR_UNPUBLISH'); ?>
		</button>
		<?php if ($canDo->get('core.create')) : ?>
			<button type="button" class="btn btn-outline-primary js-cadastro-task" data-task="<?php echo htmlspecialchars($list, ENT_QUOTES, 'UTF-8'); ?>.duplicate">
				<?php echo Text::_('JTOOLBAR_DUPLICATE'); ?>
			</button>
		<?php endif; ?>
		<button type="button" class="btn btn-outline-warning js-cadastro-task" data-task="<?php echo htmlspecialchars($list, ENT_QUOTES, 'UTF-8'); ?>.trash">
			<?php echo Text::_('JTOOLBAR_TRASH'); ?>
		</button>
		<button type="button" class="btn btn-outline-secondary js-cadastro-task" data-task="<?php echo htmlspecialchars($list, ENT_QUOTES, 'UTF-8'); ?>.checkin">
			<?php echo Text::_('JTOOLBAR_CHECKIN'); ?>
		</button>
	<?php endif; ?>

	<?php if ($filterState === '-2' && $canDo->get('core.delete')) : ?>
		<button type="button" class="btn btn-outline-danger js-cadastro-task" data-task="<?php echo htmlspecialchars($list, ENT_QUOTES, 'UTF-8'); ?>.delete">
			<?php echo Text::_('JTOOLBAR_EMPTY_TRASH'); ?>
		</button>
	<?php endif; ?>
</div>
