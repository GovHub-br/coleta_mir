<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Controller;

\defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use \Joomla\CMS\Application\SiteApplication;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Multilanguage;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Controller\BaseController;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Uri\Uri;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\HTML\HTMLHelper;

/**
 * Adesao class.
 *
 * @since  1.6.0
 */
class AdesaoController extends BaseController
{
	/**
	 * Method to check out an item for editing and redirect to the edit form.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 *
	 * @throws  Exception
	 */
	public function edit()
	{
		// Get the previous edit id (if any) and the current edit id.
		$previousId = (int) $this->app->getUserState('com_edital.edit.adesao.id');
		$editId     = $this->input->getInt('id', 0);

		// Set the user id for the user to edit in the session.
		$this->app->setUserState('com_edital.edit.adesao.id', $editId);

		// Get the model.
		$model = $this->getModel('Adesao', 'Site');

		// Check out the item
		if ($editId)
		{
			$model->checkout($editId);
		}

		// Check in the previous user.
		if ($previousId && $previousId !== $editId)
		{
			$model->checkin($previousId);
		}

		// Redirect to the edit screen.
		$this->setRedirect(Route::_('index.php?option=com_edital&view=adesaoform&layout=edit', false));
	}

	/**
	 * Method to save data
	 *
	 * @return    void
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function publish()
	{
		// Checking if the user can remove object
		$user = $this->app->getIdentity();

		if ($user->authorise('core.edit', 'com_edital') || $user->authorise('core.edit.state', 'com_edital'))
		{
			$model = $this->getModel('Adesao', 'Site');

			// Get the user data.
			$id    = $this->input->getInt('id');
			$state = $this->input->getInt('state');

			// Attempt to save the data.
			$return = $model->publish($id, $state);

			// Check for errors.
			if ($return === false)
			{
				$this->setMessage(Text::sprintf('Save failed: %s', $model->getError()), 'warning');
			}

			// Clear the profile id from the session.
			$this->app->setUserState('com_edital.edit.adesao.id', null);

			// Flush the data from the session.
			$this->app->setUserState('com_edital.edit.adesao.data', null);

			// Redirect to the list screen.
			$this->setMessage(Text::_('COM_EDITAL_ITEM_SAVED_SUCCESSFULLY'));
			$menu = Factory::getApplication()->getMenu();
			$item = $menu->getActive();

			if (!$item)
			{
				// If there isn't any menu item active, redirect to list view
				$this->setRedirect(Route::_('index.php?option=com_edital&view=adesoes', false));
			}
			else
			{
				$this->setRedirect(Route::_('index.php?Itemid='. $item->id, false));
			}
		}
		else
		{
			throw new \Exception(500);
		}
	}

	/**
	 * Check in record
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.0.0
	 */
	public function checkin()
	{
		// Check for request forgeries.
		$this->checkToken('GET');

		$id 	= $this->input->getInt('id', 0);
		$model 	= $this->getModel();
		$item 	= $model->getItem($id);

		// Checking if the user can remove object
		$user = $this->app->getIdentity();

		if ($user->authorise('core.manage', 'com_edital') || $item->checked_out == $user->id) { 

			$return = $model->checkin($id);

			if ($return === false)
			{
				// Checkin failed.
				$message = Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError());
				$this->setRedirect(Route::_('index.php?option=com_edital&view=adesao' . '&id=' . $id, false), $message, 'error');
				return false;
			}
			else
			{
				// Checkin succeeded.
				$message = Text::_('COM_EDITAL_CHECKEDIN_SUCCESSFULLY');
				$this->setRedirect(Route::_('index.php?option=com_edital&view=adesao' . '&id=' . $id, false), $message);
				return true;
			}
		}
		else
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	/**
	 * Remove data
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function remove()
	{
		// Checking if the user can remove object
		$user = $this->app->getIdentity();

		if ($user->authorise('core.delete', 'com_edital'))
		{
			$model = $this->getModel('Adesao', 'Site');

			// Get the user data.
			$id = $this->input->getInt('id', 0);

			// Attempt to save the data.
			$return = $model->delete($id);

			// Check for errors.
			if ($return === false)
			{
				$this->setMessage(Text::sprintf('Delete failed', $model->getError()), 'warning');
			}
			else
			{
				// Check in the profile.
				if ($return)
				{
					$model->checkin($return);
				}

				$this->app->setUserState('com_edital.edit.adesao.id', null);
				$this->app->setUserState('com_edital.edit.adesao.data', null);

				$this->app->enqueueMessage(Text::_('COM_EDITAL_ITEM_DELETED_SUCCESSFULLY'), 'success');
				$this->app->redirect(Route::_('index.php?option=com_edital&view=adesoes', false));
			}

			// Redirect to the list screen.
			$menu = Factory::getApplication()->getMenu();
			$item = $menu->getActive();
			$this->setRedirect(Route::_($item->link, false));
		}
		else
		{
			throw new \Exception(500);
		}
	}

	/**
	 * Generate PDF receipt for adesao
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function generatepdf()
	{
		// Check for request forgeries
		$this->checkToken('GET');
		
		$id = $this->input->getInt('id', 0);
		
		if (!$id) {
			throw new \Exception(Text::_('COM_EDITAL_ERROR_INVALID_ID'), 400);
		}

		// Get the model and item
		$model = $this->getModel('Adesao', 'Site');
		$item = $model->getItem($id);
		
		if (!$item || !$item->id) {
			throw new \Exception(Text::_('COM_EDITAL_ERROR_ITEM_NOT_FOUND'), 404);
		}

		// Load TCPDF
		require_once JPATH_LIBRARIES . '/tcpdf/tcpdf.php';

		// Create new PDF document
		$pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

		// Set document information
		$pdf->SetCreator('SINAPIR');
		$pdf->SetAuthor('Sistema SINAPIR');
		$pdf->SetTitle('Comprovante de Protocolo - SINAPIR');
		$pdf->SetSubject('Comprovante de Adesão');

		// Remove default header/footer
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);

		// Set margins
		$pdf->SetMargins(15, 15, 15);
		$pdf->SetAutoPageBreak(true, 15);

		// Set font
		$pdf->SetFont('helvetica', '', 10);

		// Add a page
		$pdf->AddPage();

		// Generate protocol number
		$protocolNumber = AdesaoHelper::getProtocoloAdesao($item->id);
		
		// Format date
		$dataEnvio = HTMLHelper::_('date', $item->dt_criacao, 'd \d\e F \d\e Y, \à\s H:i');

		$esfera = $item->tp_esfera_governamental == 'municipal' ? 'Município' : ($item->tp_esfera_governamental == 'estadual' ? 'Estado' : 'Consórcios/Associações de Municípios');

		if($esfera != 'Estado'){
			$itensEsfera = $item->ds_municipios;
		}else{			
			$itensEsfera = $item->sg_estado;
		}

		// Build HTML content
		$html = $this->buildPdfContent($item, $protocolNumber, $dataEnvio, $esfera, $itensEsfera);

		// echo $html;die;
		// Write HTML content
		$pdf->writeHTML($html, true, false, true, false, '');

		// Set filename
		$filename = 'Comprovante_Protocolo_' . $protocolNumber . '.pdf';

		// Output PDF
		$pdf->Output($filename, 'D'); // 'D' for download
		
		// Close the application to prevent any additional output
		Factory::getApplication()->close();
	}

	/**
	 * Build PDF content HTML
	 *
	 * @param object $item           The adesao item
	 * @param string $protocolNumber The protocol number
	 * @param string $dataEnvio      Formatted submission date
	 *
	 * @return string HTML content for PDF
	 */
	private function buildPdfContent($item, $protocolNumber, $dataEnvio, $esfera, $itensEsfera)
	{
		// Load HTML template
		$templatePath = JPATH_SITE . '/components/com_edital/tmpl/pdf/comprovante.html';
		
		if (!file_exists($templatePath)) {
			throw new \Exception('Template PDF não encontrado: ' . $templatePath);
		}
		
		$html = file_get_contents($templatePath);
		
		// Define variables for replacement
		$variables = [
			'{{PROTOCOL_NUMBER}}'  => $protocolNumber,
			'{{STATUS}}'           => 'Aguardando análise',
			'{{DATA_ENVIO}}'       => $dataEnvio,
			'{{ORGAO_ENTIDADE}}'   => htmlspecialchars($item->no_orgao_entidade ?? ''),
			'{{CNPJ}}'             => htmlspecialchars($item->nu_cnpj ?? ''),
			'{{DATA_GERACAO}}'     => HTMLHelper::_('date', date('Y-m-d H:i:s'), 'd/m/Y \à\s H:i'),
			'{{ESFERA}}'          => $esfera,
			'{{LABEL_ITENS_ESFERA}}' => $esfera,
			'{{ITENS_ESFERA}}'          => $itensEsfera,
		];
		
		// Replace variables in template
		$html = str_replace(array_keys($variables), array_values($variables), $html);
		
		return $html;
	}
}
