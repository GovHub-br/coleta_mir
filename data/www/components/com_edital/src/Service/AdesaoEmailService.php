<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Service;

use Comedital\Component\Edital\Site\Helper\EditalMailHelper;

\defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Mail\MailerFactoryInterface;

class AdesaoEmailService
{
    /**
     * Envia email de notificação de evento para o responsável da adesão.
     *
     * @param int    $adesaoId          ID da adesão
     * @param string $templateName      Nome do arquivo de template (dentro de tmpl/email/)
     * @param string $assunto           Assunto do email (sem o prefixo do site)
     * @param array  $extraPlaceholders Placeholders extras específicos do evento
     *
     * @return void
     */
    public function enviarEmailEvento(int $adesaoId, string $templateName, string $assunto, array $extraPlaceholders = []): void
    {
        $config = Factory::getConfig();
        $siteName = $config->get('sitename', 'SINAPIR');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('no_oficial'),
                $db->quoteName('dt_criacao'),
                $db->quoteName('tp_esfera_governamental'),
                $db->quoteName('ds_municipios'),
                $db->quoteName('sg_estado'),
                $db->quoteName('ds_email_responsavel'),
                $db->quoteName('ds_emails_alternativos'),
            ])
            ->from($db->quoteName('#__tb_adesao'))
            ->where($db->quoteName('id') . ' = ' . (int) $adesaoId);
        $db->setQuery($query);
        $adesao = $db->loadObject();

        if (!$adesao || empty($adesao->ds_email_responsavel)) {
            return;
        }

        $protocolo = AdesaoHelper::getProtocoloAdesao($adesao->id);
        $dataEvento = HTMLHelper::_('date', 'now', 'd \\d\\e F \\d\\e Y, \\à\\s H:i');
        $esfera = $adesao->tp_esfera_governamental === 'municipal'
            ? 'Município'
            : ($adesao->tp_esfera_governamental === 'estadual' ? 'Estado' : 'Consórcios/Associações de Municípios');
        $itensEsfera = ($esfera !== 'Estado') ? $adesao->ds_municipios : $adesao->sg_estado;

        $placeholders = array_merge([
            '{{NOME_OFICIAL}}'       => $adesao->no_oficial,
            '{{NUMERO_PROTOCOLO}}'   => $protocolo,
            '{{ESFERA}}'             => $esfera,
            '{{LABEL_ITENS_ESFERA}}' => $esfera,
            '{{ITENS_ESFERA}}'       => $itensEsfera,
            '{{DATA_EVENTO}}'        => $dataEvento,
        ], $extraPlaceholders);

        $html = $this->aplicarTemplate($templateName, $placeholders, $siteName);

        EditalMailHelper::sendEmail(
            $adesao->ds_email_responsavel,
            '[' . $siteName . '] ' . $assunto,
            $html
        );
    }

    /**
     * Aplica os placeholders no template do e-mail e renderiza o layout do HTML final.
     *
     * @param string $templateName Nome do arquivo de template dentro de tmpl/email/
     * @param array  $placeholders Placeholders a serem substituídos no template
     * @param string $siteName     Nome do site para renderização do layout
     *
     * @return string
     */
    public function aplicarTemplate(string $templateName, array $placeholders, string $siteName): string
    {
        $templatePath = JPATH_SITE . '/components/com_edital/tmpl/email/' . $templateName;

        if (!file_exists($templatePath)) {
            throw new \Exception('Template de email não encontrado: ' . $templatePath);
        }

        $html = file_get_contents($templatePath);

        foreach ($placeholders as $placeholder => $valor) {
            $html = str_replace((string) $placeholder, (string) $valor, $html);
        }

        return LayoutHelper::render('joomla.mail.mailtemplate', [
            'mail'  => $html,
            'extra' => [
                'logoUrl'  => Uri::root() . 'images/logo/logo_email.png',
                'siteName' => $siteName,
                'lang'     => 'pt-BR',
            ],
        ], JPATH_SITE . '/templates/shaper_helixultimate/html/layouts');
    }
}
