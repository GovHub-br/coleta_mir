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

$canEdit = Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_edital.' . $this->item->id);

if (!$canEdit && Factory::getApplication()->getIdentity()->authorise('core.edit.own', 'com_edital' . $this->item->id)) {
	$canEdit = Factory::getApplication()->getIdentity()->id == $this->item->created_by;
}

// Add custom CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesao.css');

#FIXME
// breadcrumbs provisorio até estrutura definitiva da hierarquia das paginas do componente


?>

<div id="sp-title" class="col-lg-12 ">
	<div class="sp-column ">
		<div class="sp-page-title">
			<div class="container">
				<nav class="mod-breadcrumbs__wrapper" aria-label="Breadcrumbs">
					<ol class="mod-breadcrumbs breadcrumb py-2">
						<li class="mod-breadcrumbs__divider float-start">
							<i class="fa-solid fa-house-chimney"></i> &nbsp;
						</li>

						<li class="mod-breadcrumbs__item breadcrumb-item"><a href="/" class="pathway"><span>Início</span></a></li>
						<li class="mod-breadcrumbs__item breadcrumb-item"><a href="<?php echo Route::_('index.php?option=com_edital&view=adesaoform&Itemid=114'); ?>"><span>Solicitação de Adesão</span></a></li>
						<li class="mod-breadcrumbs__item breadcrumb-item active"><span>Confirmação</span></li>
					</ol>
				</nav>
			</div>
		</div>
	</div>
</div>

<?php 
	if (empty($this->item->co_adesao_origem) ) {
		include 'page-protocolo.php';
	}else{
		include 'page-protocolo-complementacao.php';
	}
?>

<br/>
<br/>
<br/>