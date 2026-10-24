<?php defined('_JEXEC') or die; 

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

?>
<?php $currentView = Factory::getApplication()->input->getCmd('view'); ?>
<nav class="menu-formularios-ente-container" aria-label="Navegação da adesão">
    <div class="container">
        <ul class="menu-formularios-ente nav">
            <li class="nav-item">
                <a href="<?php echo Route::_('index.php?option=com_edital&view=minhaadesao'); ?>" class="menu-link nav-link<?php echo $currentView === 'minhaadesao' ? ' active' : ''; ?>">
                    <i class="fas fa-home" aria-hidden="true"></i>
                Minha adesão
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo Route::_('index.php?option=com_edital&view=formularios'); ?>" class="menu-link nav-link<?php echo $currentView === 'formularios' ? ' active' : ''; ?>">
                    <i class="fas fa-file" aria-hidden="true"></i>
                Meus formulários
                </a>
            </li>
        </ul>
    </div>
</nav>