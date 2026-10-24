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
use \Comedital\Component\Edital\Site\Helper\AdesaoHelper;

// Add custom CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesao.css');
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaorespostas.css');

// Add some custom styles for the list
$this->document->addStyleDeclaration('
.adesoes-list-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.adesoes-header {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border-left: 4px solid #007bff;
}

.adesoes-header h1 {
    margin: 0;
    color: #333;
    font-size: 28px;
}

.adesoes-header p {
    margin: 5px 0 0 0;
    color: #666;
    font-size: 16px;
}

.adesao-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.adesao-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.adesao-card-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.adesao-protocolo {
    font-weight: bold;
    color: #007bff;
    font-size: 18px;
}

.adesao-date {
    color: #666;
    font-size: 14px;
    padding-left: 10px;
}

.adesao-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-weight: bold;
    color: #555;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.info-value {
    color: #333;
    font-size: 14px;
}

.adesao-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.btn-visualizar {
    background: #007bff;
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.3s ease;
}

.btn-visualizar:hover {
    background: #0056b3;
    color: white;
    text-decoration: none;
}

.situacao-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.situacao-pendente {
    background: #fff3cd;
    color: #856404;
}

.situacao-aprovada {
    background: #d4edda;
    color: #155724;
}

.situacao-rejeitada {
    background: #f8d7da;
    color: #721c24;
}

.situacao-em-analise {
    background: #d1ecf1;
    color: #0c5460;
}

.no-adesoes {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-adesoes i {
    display: block;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .adesao-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .adesao-info {
        grid-template-columns: 1fr;
    }
    
    .adesao-actions {
        justify-content: flex-start;
    }
}
');
?>

<div class="adesoes-list-container">
    <!-- Cabeçalho da Lista -->
    <div class="adesoes-header">
        <h1>
            Lista de Adesões
        </h1>
        <p>
            Visualize e gerencie todas as solicitações de adesão recebidas.
            Total de adesões: <strong><?php echo count($this->items); ?></strong>
        </p>
    </div>

    <!-- Lista de Adesões -->
    <?php if (!empty($this->items)): ?>
        <?php foreach ($this->items as $adesao): ?>
            <?php 
            
                $protocolo = AdesaoHelper::getProtocoloAdesao($adesao->id);
                $viewUrl = Route::_('index.php?option=com_edital&view=adesaorespostas&id=' . $adesao->id.'&Itemid='.$this->listMenuId);
            ?>
            <div class="adesao-card">
                <div class="adesao-card-header">
                    <div class="adesao-protocolo">
                        <i class="fas fa-file-alt"></i>
                        <?php echo htmlspecialchars($protocolo); ?>
                    </div>
                    <div class="adesao-date">
                        <?php echo HTMLHelper::_('date', $adesao->dt_criacao, 'd/m/Y H:i'); ?>
                    </div>
                </div>

                <div class="adesao-info">
                    <div class="info-item">
                        <div class="info-label">Tipo de Solicitante</div>
                        <div class="info-value"><?php 
                            switch ($adesao->tp_esfera_governamental) {
                                case 'estadual':
                                    echo 'Estado';
                                    break;
                                case 'municipal':
                                    echo 'Município';
                                    break;
                                case 'consorcio':
                                    echo 'Consórcios/Associações';
                                    break;
                                default:
                                    echo ucfirst($adesao->tp_esfera_governamental);
                            }
                        ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Nome Oficial</div>
                        <div class="info-value"><?php echo htmlspecialchars($adesao->no_oficial); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">CNPJ</div>
                        <div class="info-value"><?php echo htmlspecialchars($adesao->ds_cnpj); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">Estado</div>
                        <div class="info-value"><?php echo htmlspecialchars($adesao->sg_estado); ?></div>
                    </div>

                    <?php if (!empty($adesao->ds_municipios) && in_array($adesao->tp_esfera_governamental, ['municipal', 'consorcio'])): ?>
                    <div class="info-item">
                        <div class="info-label">Município(s)</div>
                        <div class="info-value"><?php echo htmlspecialchars($adesao->ds_municipios); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($adesao->edital_titulo)): ?>
                    <div class="info-item">
                        <div class="info-label">Edital</div>
                        <div class="info-value"><?php echo htmlspecialchars($adesao->edital_titulo); ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="situacao-badge situacao-<?php echo strtolower(str_replace(' ', '-', $adesao->situacao ?? 'pendente')); ?>">
                                <?php echo htmlspecialchars($adesao->situacao ?? 'Pendente'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="adesao-actions">
                    <a href="<?php echo $viewUrl; ?>" 
                       class="btn-visualizar">
                        <i class="fas fa-eye"></i>
                        Visualizar Respostas
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-adesoes">
            <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
            <h3 style="color: #999;">Nenhuma adesão encontrada</h3>
            <p>Não há solicitações de adesão registradas no sistema.</p>
        </div>
    <?php endif; ?>
</div>