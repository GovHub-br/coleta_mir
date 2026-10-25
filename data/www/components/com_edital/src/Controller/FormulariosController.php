<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Comedital\Component\Edital\Site\Helper\EditalHelper;

/**
 * Formularios class.
 *
 * @since  1.0.0
 */
class FormulariosController extends FormController
{
	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional
	 * @param   array   $config  Configuration array for model. Optional
	 *
	 * @return  object	The model
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Formularios', $prefix = 'Site', $config = array())
	{
		return parent::getModel($name, $prefix, array('ignore_request' => true));
	}

	/**
	 * Generate PDF receipt for adesao
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function pdfcomprovante()
	{
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


		$noEdital = $item->co_edital ?? '';

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


		// Build HTML content
		$html = $this->buildPdfContent($item, $protocolNumber, $dataEnvio, $noEdital); 

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
	private function buildPdfContent($item, $protocolNumber, $dataEnvio, $noEdital)
	{
		// Load HTML template
		$templatePath = JPATH_SITE . '/components/com_edital/tmpl/pdf/comprovante-formulario-enviado.html';
		$app = Factory::getApplication();
		$noSite = $app->get('sitename');
		if (!file_exists($templatePath)) {
			throw new \Exception('Template PDF não encontrado: ' . $templatePath);
		}
		
		$html = file_get_contents($templatePath);
		
		// Define variables for replacement
		$variables = [
			'{{PROTOCOL_NUMBER}}'  => $protocolNumber,
			'{{STATUS}}'           => 'Enviado',
			'{{NOME_RESPONSAVEL}}' => htmlspecialchars($item->no_oficial ?? '—'),
			'{{DATA_ENVIO}}'       => $dataEnvio,
			'{{DATA_GERACAO}}'     => HTMLHelper::_('date', date('Y-m-d H:i:s'), 'd/m/Y \à\s H:i'),
			'{{NO_EDITAL}}'        => htmlspecialchars($noEdital ?? ''),
			'{{NO_SITE}}'           => $noSite,
		];
		
		// Replace variables in template
		$html = str_replace(array_keys($variables), array_values($variables), $html);
		
		return $html;
	}
}
