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

$app = \Joomla\CMS\Factory::getApplication();
$layout = $app->input->getCmd('layout', 'default');

if($this->item){
    include 'item.php';
}else if($layout === 'recurso'){
    include 'recurso.php';
}else{
    include 'list.php';
}