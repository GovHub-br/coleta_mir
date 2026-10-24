<?php

/**
 * Entry page for the new adhesion form.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaoentrada.css');

$app = Factory::getApplication();
$activeMenu = $app->getMenu()->getActive();
$itemIdQuery = $activeMenu ? '&Itemid=' . (int) $activeMenu->id : '';
$urlIniciar = Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar' . $itemIdQuery);
$urlPreview = Route::_('index.php?option=com_edital&view=adesaoform&layout=preview' . $itemIdQuery);
?>

<main class="adesao-entry" aria-labelledby="adesao-entry-title">
    <div class="adesao-entry-intro">
        <span class="adesao-entry-kicker">Adesão ao SINAPIR</span>
        <h1 id="adesao-entry-title">Como deseja continuar?</h1>
        <p>
            Você pode consultar previamente todos os campos e documentos necessários
            ou iniciar agora o preenchimento da solicitação.
        </p>
    </div>

    <div class="adesao-entry-options">
        
        <article class="adesao-entry-card">
            <div class="adesao-entry-icon" aria-hidden="true">
                <i class="fa-regular fa-eye"></i>
            </div>
            <div>
                <h2>Ver formulário</h2>
                <p>Visualize todas as etapas, campos obrigatórios e documentos sem precisar preencher nada.</p>
            </div>
            <a href="<?php echo $urlPreview; ?>" class="btn btn-outline-primary">
                Ver formulário <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </article>

        <article class="adesao-entry-card adesao-entry-card-primary">
            <div class="adesao-entry-icon" aria-hidden="true">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <div>
                <h2>Iniciar adesão</h2>
                <p>Preencha os dados, envie os documentos e salve seu progresso como rascunho.</p>
            </div>
            <a href="<?php echo $urlIniciar; ?>" class="btn btn-primary">
                Iniciar adesão <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </article>
    </div>
</main>
