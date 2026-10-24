<?php

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

class ConstructorlistaController extends FormController
{
	public function getModel($name = 'Construtorlista', $prefix = 'Site', $config = array())
	{
		return parent::getModel($name, $prefix, array('ignore_request' => true));
	}
}
