<?php defined('_JEXEC') or die; 

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

?>
<?php $currentView = Factory::getApplication()->input->getCmd('view'); ?>
<nav class="menu-formularios-ente-container" aria-label="Navegação da adesão">
    <div class="container">
        <ul class="menu-formularios-ente nav">
            <li class="nav-item">
                <a href="<?php echo Route::_('index.php?option=com_edital&view=adesaogestao'); ?>" class="menu-link nav-link<?php echo $currentView === 'adesaogestao' ? ' active' : ''; ?>">
                    <i class="fas fa-home" aria-hidden="true"></i>
                Painel de Gestão
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo Route::_('index.php?option=com_edital&view=formulariogestao'); ?>" class="menu-link nav-link<?php echo $currentView === 'formularios' ? ' active' : ''; ?>">
                    <i class="fas fa-file" aria-hidden="true"></i>
                    Gestão de Formulários
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo Route::_('index.php?option=com_edital&view=construtorlista'); ?>" class="menu-link nav-link<?php echo $currentView === 'construtorlista' ? ' active' : ''; ?>">
                    <i class="fa fa-pencil" aria-hidden="true"></i>
                    Construtor de Formulários
                </a>
            </li>
        </ul>
    </div>
</nav>