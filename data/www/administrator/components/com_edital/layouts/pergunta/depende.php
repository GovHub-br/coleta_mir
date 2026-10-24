<?php

/**
 * Lista de dependência da pergunta.
 *
 * O layout padrão do fancy-select pode ser substituído pelo template e,
 * nessa substituição, o atributo data-filter das opções se perde. Sem ele,
 * o filtro por edital oculta todas as perguntas.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

extract($displayData);

$html = [];
$attr = '';

$attr .= !empty($size) ? ' size="' . $size . '"' : '';
$attr .= $multiple ? ' multiple' : '';
$attr .= $autofocus ? ' autofocus' : '';
$attr .= $onchange ? ' onchange="' . $onchange . '"' : '';
$attr .= $dataAttribute;
$attr .= ' data-max-results="300"';

if ($readonly || $disabled) {
    $attr .= ' disabled="disabled"';
}

$attr2  = '';
$attr2 .= !empty($class) ? ' class="' . $class . '"' : '';
$attr2 .= ' placeholder="' . $this->escape($hint ?: Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS')) . '" ';

if ($required) {
    $attr  .= ' required class="required"';
    $attr2 .= ' required';
}

$listoptions = [
    'option.key'     => 'value',
    'option.text'    => 'text',
    'list.select'    => $value,
    'id'             => $id,
    'list.translate' => false,
    'option.attr'    => 'optionattr',
    'list.attr'      => trim($attr),
];

if ($readonly) {
    $html[] = HTMLHelper::_('select.genericlist', $options, '', $listoptions);

    if ($multiple && is_array($value)) {
        if (!count($value)) {
            $value[] = '';
        }

        foreach ($value as $val) {
            $html[] = '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($val, ENT_COMPAT, 'UTF-8') . '">';
        }
    } else {
        $html[] = '<input type="hidden" id="' . $id . '-value" name="' . $name . '" value="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '">';
    }
} else {
    $html[] = HTMLHelper::_('select.genericlist', $options, $name, $listoptions);
}

Text::script('JGLOBAL_SELECT_NO_RESULTS_MATCH');
Text::script('JGLOBAL_SELECT_PRESS_TO_SELECT');

Factory::getApplication()->getDocument()->getWebAssetManager()
    ->usePreset('choicesjs')
    ->useScript('webcomponent.field-fancy-select');

?>
<joomla-field-fancy-select <?php echo $attr2; ?>><?php echo implode($html); ?></joomla-field-fancy-select>
