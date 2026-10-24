<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$canDo      = $displayData['canDo'];
$form       = $displayData['formController'];
$checkedOut = !empty($displayData['checkedOut']);
$canSave    = !$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'));
$formEsc    = htmlspecialchars($form, ENT_QUOTES, 'UTF-8');
?>
<div class="com-edital-cadastro__toolbar d-flex flex-wrap gap-2 mb-3">
	<?php if ($canSave) : ?>
		<button type="button" class="btn btn-primary js-cadastro-task" data-task="<?php echo $formEsc; ?>.apply">
			<?php echo Text::_('JTOOLBAR_APPLY'); ?>
		</button>
		<button type="button" class="btn btn-success js-cadastro-task" data-task="<?php echo $formEsc; ?>.save">
			<?php echo Text::_('JTOOLBAR_SAVE'); ?>
		</button>
		<?php if ($canDo->get('core.create')) : ?>
			<button type="button" class="btn btn-outline-success js-cadastro-task" data-task="<?php echo $formEsc; ?>.save2new">
				<?php echo Text::_('JTOOLBAR_SAVE_AND_NEW'); ?>
			</button>
		<?php endif; ?>
	<?php endif; ?>
	<button type="button" class="btn btn-secondary js-cadastro-task" data-task="<?php echo $formEsc; ?>.cancel">
		<?php echo Text::_('JTOOLBAR_CANCEL'); ?>
	</button>
</div>
