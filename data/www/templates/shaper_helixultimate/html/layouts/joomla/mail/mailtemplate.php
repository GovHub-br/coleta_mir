<?php

/**
 * @package     Joomla.Site
 * @subpackage  Layout
 *
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;

/** @var array{mail?: string, extra?: array<string, mixed>} $displayData */
if (!array_key_exists('mail', $displayData) || !$displayData['mail']) {
    return;
}

$mailBody = $displayData['mail'];
$extraData = $displayData['extra'] ?? [];
$logoUrl = $extraData['logoUrl'] ?? Uri::root() . 'images/logo/logo_email.png';
$siteName = $extraData['siteName'] ?? '';
$lang = $extraData['lang'] ?? 'pt-BR';
$assetRoot = $extraData['assetRoot'] ?? Uri::root();

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES); ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title></title>
    <style>
        @font-face {
            font-family: 'Rawline';
            font-style: normal;
            font-weight: 700;
            src: url('<?php echo htmlspecialchars($assetRoot, ENT_QUOTES); ?>templates/shaper_helixultimate/fonts/rawline-700.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Rawline';
            font-style: normal;
            font-weight: 400;
            src: url('<?php echo htmlspecialchars($assetRoot, ENT_QUOTES); ?>templates/shaper_helixultimate/fonts/rawline-400.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Rawline';
            font-style: normal;
            font-weight: 600;
            src: url('<?php echo htmlspecialchars($assetRoot, ENT_QUOTES); ?>templates/shaper_helixultimate/fonts/rawline-600.ttf') format('truetype');
        }

        html { height: 100%; }
        body { margin: 0; padding: 0; background-color: #f4f5f7; }
        table, td, div, h1, h2, h3, p, a { font-family: Arial, sans-serif; }
        .email-shell { width: 100%; max-width: 680px; background-color: #ffffff; }
        .portal-header { padding: 13px 28px; color: #ffffff; background-color: #1351b4; font-size: 13px; }
        .portal-header strong { font-size: 13px; }
        .portal-divider { padding: 0 12px; color: #b8d2fa; }
        .logo { padding: 28px 20px 20px; text-align: center; background-color: #ffffff; }
        .header { color: #ffffff; padding: 20px; text-align: center; background-color: #1351b4; }
        .content { padding: 22px 28px 30px; font-size: 15px; line-height: 1.65; color: #252525; }
        .protocolo { padding: 15px; margin: 20px 0; border: 2px solid #1351b4; background-color: #f0f8ff; text-align: center; }
        .info-table { width: 100%; margin: 20px 0; border-collapse: collapse; }
        .info-table td { padding: 10px; border: 1px solid #dddddd; }
        .info-label { width: 30%; background-color: #f5f5f5; font-weight: bold; }
        .footer { padding: 20px 24px; background-color: #1351b4; color: #ffffff; text-align: center; font-size: 12px; line-height: 1.6; }
        .footer p { margin: 2px 0; }
        .footer .automatic-message { margin-top: 10px; color: #dbe8fb; }
        .destaque { padding: 15px; margin: 20px 0; border: 2px solid #1a6e2e; background-color: #eafaf1; text-align: center; font-weight: bold; font-size: 16px; }
        .observacoes-box { padding: 15px; margin: 20px 0; border-left: 4px solid #1a6e2e; background-color: #f9f9f9; }
        .motivo-box { padding: 15px; margin: 20px 0; border-left: 4px solid #c0392b; background-color: #fdecea; }
        .mensagem-box { padding: 15px; margin: 20px 0; border-left: 4px solid #f0a500; background-color: #fff8e1; }
        .buttonaction { display: inline-block; box-sizing: border-box; width: auto; height: 39px; padding: 8px 24px; border: 0; border-radius: 20px; background-color: #1351b4; color: #ffffff !important; font-family: 'Rawline', Arial, sans-serif; font-size: 14px; font-style: normal; font-weight: 600; line-height: 125%; letter-spacing: 0; text-decoration: none; text-align: center; vertical-align: middle; }
        .notification-title { margin: 0 0 16px; color: #1351b4; font-family: 'Rawline', Arial, sans-serif; font-size: 16px; font-style: normal; font-weight: 700; line-height: 24px; letter-spacing: -0.16px; }
        .notification-intro { margin: 0 0 20px; font-family: 'Rawline', Arial, sans-serif; font-size: 14px; font-style: normal; font-weight: 400; line-height: 24px; letter-spacing: -0.16px; }
        .notification-details { width: 100%; margin: 20px 0; border-left: 4px solid #1351b4; border-radius: 9px; background-color: #f1f5fb; overflow: hidden; }
        .notification-details td { padding: 15px 18px; color: #344054; font-family: 'Rawline', Arial, sans-serif; font-size: 12.9px; font-style: normal; font-weight: 400; line-height: 19.5px; letter-spacing: 0; vertical-align: middle; }
        .notification-details strong { color: #123b76; font-family: 'Rawline', Arial, sans-serif; font-size: 12.9px; font-style: normal; font-weight: 700; line-height: 19.5px; letter-spacing: 0; vertical-align: middle; }
        .notification-detail-row { margin: 0 0 10px; }
        .notification-detail-row:last-child { margin-bottom: 0; }
        .notification-action { margin: 22px 0 26px; text-align: center; }
        .notification-help { padding-top: 16px; border-top: 1px solid #d9dee7; color: #72706f; font-family: 'Rawline', Arial, sans-serif; font-size: 12px; font-style: normal; font-weight: 400; line-height: 19.5px; letter-spacing: 0; vertical-align: middle; }

        @media only screen and (max-width: 600px) {
            .portal-header { padding: 12px 18px; }
            .portal-divider { display: none; }
            .portal-ministry { display: block; margin-top: 3px; }
            .content { padding: 20px 18px 24px; }
        }
    </style>
</head>
<body>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f4f5f7;">
    <tr>
        <td align="center" style="padding:24px 10px;">
            <table role="presentation" class="email-shell" width="680" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;background-color:#ffffff;">
                <tr>
                    <td class="portal-header" style="padding:13px 28px;color:#ffffff;background-color:#1351b4;font-size:13px;">
                        <strong>COLETA MIR - GOVERNO FEDERAL</strong>
                        <span class="portal-divider">|</span>
                        <span class="portal-ministry">Ministério da Igualdade Racial</span>
                    </td>
                </tr>
                <tr>
                    <td class="logo" style="padding:28px 20px 20px;text-align:center;background-color:#ffffff;">
                        <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($siteName, ENT_QUOTES); ?> Logo" style="max-height:80px;height:auto;max-width:50%;border:none;text-decoration:none;display:block;margin:0 auto;">
                    </td>
                </tr>
                <tr>
                    <td class="content" style="padding:22px 28px 30px;font-size:15px;line-height:1.65;color:#252525;">
                        <?php echo $mailBody; ?>
                    </td>
                </tr>
                <tr>
                    <td class="footer" style="padding:20px 24px;text-align:center;font-size:12px;line-height:1.6;color:#ffffff;background-color:#1351b4;">
                        <p>Coleta MIR - Acompanhamento da execução dos TEDs e convênios do MIR</p>
                        <p>Ministério da Igualdade Racial | Governo Federal © 2026</p>
                        <p class="automatic-message" style="margin-top:10px;color:#dbe8fb;">Este é um e-mail automático gerado pela plataforma, por favor não responda.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
