<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;


?>

<div class="item_fields">
<?php if ($this->params->get('show_page_heading')) : ?>
    <div class="page-header">
        <h1> <?php echo $this->escape($this->params->get('page_heading')); ?> </h1>
    </div>
    <?php endif;?>
	<table class="table">
		

		<tr>
			<th><?php echo Text::_('COM_EDITAL_FORM_LBL_EDITAL_NO_EDITAL'); ?></th>
			<td><?php echo $this->item->no_edital; ?></td>
		</tr>

		<tr>
			<th><?php echo Text::_('COM_EDITAL_FORM_LBL_EDITAL_DS_EDITAL'); ?></th>
			<td><?php echo nl2br($this->item->ds_edital); ?></td>
		</tr>

		<tr>
			<th><?php echo Text::_('COM_EDITAL_FORM_LBL_EDITAL_DT_ABERTURA'); ?></th>
			<td>				<?php
			$date = $this->item->dt_abertura;
			echo $date > 0 ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC6')) : '-';
			?>

			</td>
		</tr>

		<tr>
			<th><?php echo Text::_('COM_EDITAL_FORM_LBL_EDITAL_DT_ENCERRAMENTO'); ?></th>
			<td>				<?php
			$date = $this->item->dt_encerramento;
			echo $date > 0 ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC6')) : '-';
			?>

			</td>
		</tr>

		<tr>
			<th><?php echo Text::_('COM_EDITAL_FORM_LBL_EDITAL_DT_PUBLICACAO'); ?></th>
			<td>				<?php
			$date = $this->item->dt_publicacao;
			echo $date > 0 ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC6')) : '-';
			?>

			</td>
		</tr>

	</table>

</div>

