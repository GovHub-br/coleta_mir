<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Model\AdesaoModel;
use \Joomla\CMS\Factory;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
use \Comedital\Component\Edital\Site\Model\RespostaModel;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Language\Text;
use stdClass;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\User\UserFactoryInterface;
/**
 * Class AdesaoHelper
 *
 * @since  1.0.0
 */
class AdesaoHelper
{
    /**
     * Valores aceitos no parâmetro "esfera" dos links de pré-cadastro.
     */
    private const PREFILL_ESFERAS = [
        'estado'    => 'estadual',
        'estadual'  => 'estadual',
        'municipio' => 'municipal',
        'municipal' => 'municipal',
        'consorcio' => 'consorcio',
    ];

    /**
     * Itens do Anexo II da Portaria de adesão (estrutura e capacidade de execução orçamentária do órgão),
     * por seção: rótulo do Anexo => IDs das perguntas correspondentes. Cada item lista as perguntas do
     * edital de adesão (2) e do edital complementar dos municípios do consórcio (3); os IDs são os mesmos
     * em homologação e produção.
     */
    private const ANEXO_II_SECOES = [
        'Seção 1 - Identificação do Órgão' => [
            'a. Nome do órgão gestor da política de promoção da igualdade racial' => [21, 92],
            'b. Município'                                                         => [22, 93],
            'UF'                                                                   => [23, 94],
            'c. Endereço institucional'                                            => [24, 95],
            'CEP'                                                                  => [25, 96],
            'd. Telefone(s) institucional(is), com DDD'                            => [26, 219, 97],
            'e. E-mail(s) institucional(is)'                                       => [27, 28, 98, 99],
            'f. CNPJ'                                                              => [29, 100],
        ],
        'Seção 2 - Identificação do Titular do Órgão' => [
            'a. Nome do Titular do Órgão'                                                         => [32, 103],
            'b. Cargo do Titular do Órgão'                                                        => [33, 104],
            'c. Telefone(s), inclusive celular(es), com DDD'                                      => [34, 105],
            'd. Correio(s) eletrônico(s)'                                                         => [35, 106],
            'e. Nome do Substituto do Titular do Órgão'                                           => [36, 107],
            'f. Cargo do Substituto do Titular do Órgão'                                          => [37, 108],
            'g. Nº da Legislação (Lei, Decreto, Portaria, etc.) de designação do Substituto do Órgão' => [38, 109],
        ],
        'Seção 3 - Estrutura e Gestão' => [
            'a. Caracterização do órgão quanto à capacidade de execução orçamentária'  => [41, 112],
            'b. Quantidade de pessoas que formam a equipe de trabalho do órgão'         => [46, 117],
            'c. Equipe de Trabalho – I) Escolaridade formal'                            => [47, 118],
            'c. Equipe de Trabalho – II) Estrutura de cargos'                           => [59, 130],
            'c. Equipe de Trabalho – III) Gênero'                                       => [60, 131],
            'c. Equipe de Trabalho – III) Gênero – Outro'                               => [85, 156],
            'c. Equipe de Trabalho – IV) Cor/Raça'                                      => [84, 155],
            'd. O órgão possui sede própria?'                                           => [61, 132],
            'e. O órgão possui acesso à internet?'                                      => [62, 133],
            'f. Número de máquinas e equipamentos que o órgão possui'                   => [63, 134],
            'f. Outros equipamentos (Quais?)'                                           => [64, 135],
        ],
    ];

    /**
     * Lê e valida os parâmetros de localidade da URL, usados nos links enviados por
     * ofício aos entes que aderiram ao SINAPIR antes da plataforma.
     *
     * Parâmetros aceitos: esfera (estadual|municipal|consorcio), uf (sigla do estado),
     * mun (códigos IBGE separados por vírgula), nome (nome oficial sugerido),
     * email, email_alt e cnpj (somente dígitos).
     *
     * @param   \Joomla\Input\Input  $input  Entrada da requisição
     *
     * @return  array|null  Dados validados ou null quando o link não traz localidade válida
     */
    public static function getPrefillLocalidade($input)
    {
        $esfera = strtolower($input->get('esfera', '', 'alnum'));

        if (!isset(self::PREFILL_ESFERAS[$esfera])) {
            return null;
        }

        $esfera = self::PREFILL_ESFERAS[$esfera];
        $uf     = strtoupper($input->get('uf', '', 'alnum'));

        if (strlen($uf) !== 2) {
            return null;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(['sg_estado', 'no_estado']))
            ->from($db->quoteName('#__tb_estado'))
            ->where($db->quoteName('sg_estado') . ' = :uf')
            ->bind(':uf', $uf);

        $db->setQuery($query);
        $estado = $db->loadObject();

        if (!$estado) {
            return null;
        }

        $email    = trim($input->getString('email', ''));
        $emailAlt = trim($input->getString('email_alt', ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        if ($emailAlt !== '' && !filter_var($emailAlt, FILTER_VALIDATE_EMAIL)) {
            $emailAlt = '';
        }

        // O CNPJ é apenas sugerido no formulário: a validação de dígito verificador e a
        // checagem de adesão já existente continuam a cargo do próprio formulário.
        $cnpj = preg_replace('/\D/', '', $input->getString('cnpj', ''));

        if (strlen($cnpj) !== 14) {
            $cnpj = '';
        }

        $municipios = [];

        if ($esfera !== 'estadual') {
            // Aceita qualquer separador entre os códigos IBGE (hífen nos links atuais,
            // vírgula nos primeiros que foram gerados).
            $codigos = array_values(
                array_unique(
                    array_map(
                        'intval',
                        preg_split('/\D+/', $input->getString('mun', ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
                    )
                )
            );

            if (empty($codigos)) {
                return null;
            }

            // Município isolado adere sozinho; consórcio pode reunir vários municípios.
            if ($esfera === 'municipal') {
                $codigos = array_slice($codigos, 0, 1);
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName(['m.co_ibge', 'm.no_municipio', 'e.sg_estado']))
                ->from($db->quoteName('#__tb_municipio', 'm'))
                ->join(
                    'INNER',
                    $db->quoteName('#__tb_estado', 'e')
                    . ' ON ' . $db->quoteName('m.co_estado') . ' = ' . $db->quoteName('e.co_estado')
                )
                ->whereIn($db->quoteName('m.co_ibge'), $codigos)
                ->order($db->quoteName('m.no_municipio') . ' ASC');

            $db->setQuery($query);
            $municipios = $db->loadObjectList();

            // Qualquer código inexistente invalida o link inteiro, para não deixar
            // o ente enviar a adesão com a localidade incompleta.
            if (count($municipios) !== count($codigos)) {
                return null;
            }
        }

        return [
            'esfera'     => $esfera,
            'uf'         => $estado->sg_estado,
            'no_estado'  => $estado->no_estado,
            'municipios' => $municipios,
            'nome'       => trim($input->getString('nome', '')),
            'email'      => $email,
            'email_alt'  => $emailAlt,
            'cnpj'       => $cnpj,
        ];
    }

    public static function getAdesaoPubKey($id)
    {
        $config = Factory::getConfig();
        $cipherKey = $config->get('cipher_key', '');
        $item = base64_encode(openssl_encrypt($id, 'AES-256-CBC', $cipherKey, 0, substr(hash('sha256', $cipherKey), 0, 16)));

        return $item;
    }

    public static function getAdesaoIdByPubKey($pubKey)
    {
        $config = Factory::getConfig();
        $cipherKey = $config->get('cipher_key', '');
        $id = openssl_decrypt(base64_decode($pubKey), 'AES-256-CBC', $cipherKey, 0, substr(hash('sha256', $cipherKey), 0, 16));

        return $id;
    }

    /**
     * Gets the files attached to an item
     *
     * @param   int     $pk     The item's id
     *
     * @param   string  $table  The table's name
     *
     * @param   string  $field  The field's name
     *
     * @return  array  The files
     */
    public static function getFiles($pk, $table, $field)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select($field)
            ->from($table)
            ->where('id = ' . (int) $pk);

        $db->setQuery($query);

        return explode(',', $db->loadResult());
    }


    public static function getAdesaoById($id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select('*')
            ->from($db->quoteName('#__tb_adesao'))
            ->where('id = ' . $db->quote($id))
            ->where('state = 1')
            ->order('ordering ASC');

        $db->setQuery($query);

        return $db->loadObject();
    }


    public static function getSgEstadoOptions()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select('sg_estado AS value, no_estado AS text')
            ->from($db->quoteName('#__tb_estado'))
            ->order('no_estado ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    public static function getAdesaoByCnpj($cnpj, $idEdital)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select('*')
            ->from($db->quoteName('#__tb_adesao'))
            ->where('REPLACE(REPLACE(REPLACE(ds_cnpj, ".", ""), "/", ""), "-", "") = ' . $db->quote(preg_replace('/[^0-9]/', '', $cnpj)))
            ->where('co_edital = ' . $db->quote($idEdital))
            ->where('state = 1')
            ->order('ordering ASC');

        $db->setQuery($query);

        return $db->loadObject();
    }

    public static function getProtocoloAdesao($id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select('dt_criacao, id')
            ->from($db->quoteName('#__tb_adesao'))
            ->where('id = ' . $db->quote($id))
            ->where('state = 1')
            ->order('ordering ASC');

        $db->setQuery($query);
        $adesao = $db->loadObject();

        $protocolo = 'SINAPIR-' . HTMLHelper::_('date', $adesao->dt_criacao, 'Y') . '-' . str_pad($adesao->id, 4, '0', STR_PAD_LEFT);

        return $protocolo;
    }

    public static function getMunicipiosAdesao($dsMunicipios)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        if (isset($dsMunicipios) && $dsMunicipios != '') {
            if (is_object($dsMunicipios)) {
                $dsMunicipios = ArrayHelper::fromObject($dsMunicipios);
            }

            $values = (is_array($dsMunicipios)) ? $dsMunicipios : explode(',', $dsMunicipios);

            $textValue = array();

            foreach ($values as $value) {
                $query = "select co_ibge value, concat(no_municipio,' (', sg_estado,')') key_field from #__tb_municipio m inner join #__tb_estado e on e.co_estado = m.co_estado AND m.co_ibge = '$value' ";

                $db->setQuery($query);
                $results = $db->loadObject();

                if ($results) {
                    $textValue[] = $results->key_field;
                }
            }

            $dsMunicipios = !empty($textValue) ? implode(', ', $textValue) : $dsMunicipios;

            return $dsMunicipios;
        }
        return '';
    }

    public static function getEstatisticasAdesoes()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->select('st_adesao, COUNT(*) AS total')
            ->from($db->quoteName('#__tb_adesao'))
            ->where($db->quoteName('state') . ' = 1')
            ->group($db->quoteName('st_adesao'));

        $db->setQuery($query);
        $results = $db->loadObjectList();

        $estatisticas = new stdClass();
        $totalGeral = 0;
        foreach ($results as $result) {
            $estatisticas->{$result->st_adesao} = $result->total;
            $totalGeral += $result->total;
        }

        $estatisticas->total = $totalGeral;


        //tempo medio de análise
        $query = $db->getQuery(true);
        $query->select('AVG(DATEDIFF(mt.dt_marco, dt_criacao)) AS tempo_medio')
            ->from($db->quoteName('#__tb_adesao'))
            ->innerJoin($db->quoteName('#__tb_marco_temporal') . ' AS mt ON mt.co_adesao = ' . $db->quoteName('#__tb_adesao.id') . ' AND mt.ds_marco in (' . $db->quote('deferido') . ', ' . $db->quote('indeferido') . ')')
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('st_adesao') . ' IN (' . $db->quote('deferido') . ', ' . $db->quote('indeferido') . ')');

        $db->setQuery($query);
        $tempoMedio = $db->loadResult();
        $estatisticas->tempo_medio = $tempoMedio ? round($tempoMedio) . 'd' : '-';

        return $estatisticas;
    }

    public static function getModalidadeLabel($modalidade)
    {
        $AdesaoModel = new AdesaoModel();
        return $AdesaoModel->getModalidadeLabel($modalidade);
    }

    /**
     * Rótulo do tipo da solicitação (coluna tp_solicitacao). Vazio quando a coluna
     * ainda não existe no banco.
     */
    public static function getTipoSolicitacaoLabel($tipo): string
    {
        return match ((string) $tipo) {
            'migracao'    => 'Migração',
            'nova_adesao' => 'Nova adesão',
            default       => '',
        };
    }


    public static function getLinkAdesaoComplementar($idAdesaoOrigem, $noMunicipio, $codIbgeMunicipio, $sgEstado)
    {
        $AdesaoModel = new AdesaoModel();
        $adesaoOrigem = $AdesaoModel->getItem($idAdesaoOrigem);

        $EditalModel = new \Comedital\Component\Edital\Site\Model\EditalModel();
        $editalOrigem = $EditalModel->getItem($adesaoOrigem->co_edital_id);

        $editalDestinoId = $editalOrigem->co_edital_municipio_consorcio;

        $menu = Factory::getApplication()->getMenu();
        $itemId = null;
        foreach ($menu->getItems('component', 'com_edital') as $menuItem) {
            if (($menuItem->query['view'] ?? '') === 'adesaoform' && (int) $menuItem->getParams()->get('edital_id') === $editalDestinoId) {
                $itemId = $menuItem->id;
                break;
            }
        }

        $link = 'index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar';
        if ($itemId) {
            $link .= '&Itemid=' . $itemId;
        }

        $link .= '&origem_key=' . AdesaoHelper::getAdesaoPubKey($idAdesaoOrigem);
        $link .= '&esfera=municipal';
        $link .= '&uf=' . $sgEstado;
        $link .= '&mun=' . $codIbgeMunicipio;
        $link .= '&nome=' . $noMunicipio;

        return URI::base() . Route::_($link);
    }

    public static function getAdesaoByUserEdital($userId, $editalId)
    {
        $AdesaoModel = new AdesaoModel();
        return $AdesaoModel->getAdesaoByUserEdital($userId, $editalId);
    }

    /**
     * Limites do campo nativo Limitbox do Joomla (0 = todos).
     *
     * @return  int[]
     */
    public static function getListLimits(): array
    {
        return [5, 10, 15, 20, 25, 30, 50, 100, 200, 500, 0];
    }

    public static function sanitizeListLimit(int $limit, int $default = 10): int
    {
        return in_array($limit, self::getListLimits(), true) ? $limit : $default;
    }

    public static function getStoredListLimit(string $context, int $default = 10): int
    {
        return self::sanitizeListLimit(
            (int) Factory::getApplication()->getUserState($context, $default),
            $default
        );
    }

    public static function storeListLimit(string $context, int $limit, int $default = 10): int
    {
        $limit = self::sanitizeListLimit($limit, $default);
        Factory::getApplication()->setUserState($context, $limit);

        return $limit;
    }

    public static function getEsferaDisplay($item): string
    {
        $map = [
            'municipal' => 'Município',
            'estadual'  => 'Estado',
            'consorcio' => 'Consórcio',
            'federal'   => 'Federal',
        ];
        $esfera = (string) ($item->tp_esfera_governamental ?? '');
        $label  = $map[$esfera] ?? $esfera;

        if (in_array($esfera, ['municipal', 'consorcio'], true) && !empty($item->municipios)) {
            return (string) $item->municipios;
        }

        if (!empty($item->sg_estado)) {
            return $label . ' · ' . $item->sg_estado;
        }

        return $label;
    }

    public static function getAdesaoStatusDisplay(?string $status): array
    {
        $map = [
            'rascunho'        => ['label' => 'Rascunho', 'bg' => '#eef2f7', 'color' => '#4a5568', 'icon' => 'far fa-edit'],
            'pendente'        => ['label' => 'Em análise', 'bg' => '#fde8d4', 'color' => '#f97a1f', 'icon' => 'far fa-clock'],
            'analise'         => ['label' => 'Em análise', 'bg' => '#fde8d4', 'color' => '#f97a1f', 'icon' => 'far fa-clock'],
            ''                => ['label' => 'Em análise', 'bg' => '#fde8d4', 'color' => '#f97a1f', 'icon' => 'far fa-clock'],
            'complementacao'  => ['label' => 'Em Complementação', 'bg' => '#fff4e5', 'color' => '#f0a500', 'icon' => 'far fa-comment-alt'],
            'assinatura'      => ['label' => 'Aguardando Assinatura', 'bg' => '#fff4e5', 'color' => '#f0a500', 'icon' => 'far fa-clock'],
            'deferido'        => ['label' => 'Deferido', 'bg' => '#eaf6ee', 'color' => '#28a745', 'icon' => 'far fa-check-circle'],
            'indeferido'      => ['label' => 'Indeferido', 'bg' => '#fdecea', 'color' => '#e74c3c', 'icon' => 'far fa-times-circle'],
            'recurso'         => ['label' => 'Recurso', 'bg' => '#fff4e5', 'color' => '#f0a500', 'icon' => 'fas fa-gavel'],
        ];

        return $map[$status] ?? $map['pendente'];
    }

    public static function renderListReportHtml(string $title, string $subtitle, string $itemsHtml, string $statsHtml = ''): string
    {
        $templatePath = JPATH_SITE . '/components/com_edital/tmpl/pdf/relatorio_lista.html';

        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template PDF não encontrado.');
        }

        $logoPath = JPATH_SITE . '/images/logo/logo_comprovante.png';
        $logoHtml = is_file($logoPath) ? '<img src="' . $logoPath . '" width="110"/>' : '';

        $dataGeracao = HTMLHelper::_('date', date('Y-m-d H:i:s'), 'd/m/Y \à\s H:i');
        $subtitleFull = trim($subtitle);
        $subtitleFull = ($subtitleFull !== '' ? $subtitleFull . ' · ' : '')
            . Text::sprintf('COM_EDITAL_RELATORIO_DATA_EMISSAO', $dataGeracao);

        return str_replace(
            ['{{TITLE}}', '{{SUBTITLE}}', '{{STATS}}', '{{ITEMS}}', '{{LOGO}}', '{{DATA_GERACAO}}'],
            [
                htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($subtitleFull, ENT_QUOTES, 'UTF-8'),
                $statsHtml,
                $itemsHtml,
                $logoHtml,
                htmlspecialchars($dataGeracao, ENT_QUOTES, 'UTF-8'),
            ],
            file_get_contents($templatePath)
        );
    }

    public static function renderGestaoStatsHtml(object $stats): string
    {
        return self::renderStatsRowHtml([
            ['label' => 'Total', 'value' => $stats->total ?? 0, 'color' => '#222222', 'icon' => 'fas fa-chart-bar', 'iconColor' => '#cccccc'],
            ['label' => 'Em análise', 'value' => $stats->pendente ?? 0, 'color' => '#f0a500', 'icon' => 'far fa-clock', 'iconColor' => '#f4b183'],
            ['label' => 'Em Complementação', 'value' => $stats->complementacao ?? 0, 'color' => '#f0a500', 'icon' => 'far fa-comment-alt', 'iconColor' => '#f0a500'],
            ['label' => 'Deferidos', 'value' => $stats->deferido ?? 0, 'color' => '#28a745', 'icon' => 'far fa-check-circle', 'iconColor' => '#28a745'],
            ['label' => 'Indeferidos', 'value' => $stats->indeferido ?? 0, 'color' => '#e74c3c', 'icon' => 'far fa-times-circle', 'iconColor' => '#e74c3c'],
            ['label' => 'Recursos', 'value' => $stats->recurso ?? 0, 'color' => '#f0a500', 'icon' => 'fas fa-gavel', 'iconColor' => '#f0a500'],
        ]);
    }

    public static function renderPreenchimentoStatsHtml(object $stats): string
    {
        return self::renderStatsRowHtml([
            ['label' => Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_TOTAL'), 'value' => $stats->total ?? 0, 'color' => '#222222', 'icon' => 'far fa-edit', 'iconColor' => '#cccccc'],
            ['label' => Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_MUNICIPIOS'), 'value' => $stats->municipal ?? 0, 'color' => '#f0a500', 'icon' => 'fas fa-city', 'iconColor' => '#f4b183'],
            ['label' => Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_ESTADOS'), 'value' => $stats->estadual ?? 0, 'color' => '#f0a500', 'icon' => 'fas fa-map-marker-alt', 'iconColor' => '#f0a500'],
            ['label' => Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_CONSORCIOS'), 'value' => $stats->consorcio ?? 0, 'color' => '#222222', 'icon' => 'fas fa-handshake', 'iconColor' => '#cccccc'],
            ['label' => Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_RECENTES'), 'value' => $stats->recentes ?? 0, 'color' => '#28a745', 'icon' => 'far fa-clock', 'iconColor' => '#28a745'],
            ['label' => Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_PARADAS'), 'value' => $stats->paradas ?? 0, 'color' => '#e74c3c', 'icon' => 'far fa-pause-circle', 'iconColor' => '#e74c3c'],
        ]);
    }

    public static function renderStatsRowHtml(array $cards): string
    {
        if ($cards === []) {
            return '';
        }

        $count    = count($cards);
        $gapPct   = $count > 1 ? 0.8 : 0;
        $cardPct  = number_format((100 - ($gapPct * ($count - 1))) / $count, 2, '.', '');
        $gapPct   = number_format($gapPct, 2, '.', '');
        $html     = '<table cellpadding="0" cellspacing="0" width="100%"><tr>';

        foreach ($cards as $i => $card) {
            if ($i > 0) {
                $html .= '<td width="' . $gapPct . '%">&nbsp;</td>';
            }

            $html .= '<td class="stat-card" width="' . $cardPct . '%" valign="middle" style="border:1px solid #e8e8e8;">'
                . '<table cellpadding="6" cellspacing="0" width="100%"><tr>'
                . '<td width="76%" valign="middle">'
                . '<span class="stat-label">' . htmlspecialchars((string) $card['label'], ENT_QUOTES, 'UTF-8') . '</span><br/>'
                . '<span class="stat-number" style="color:' . htmlspecialchars((string) $card['color'], ENT_QUOTES, 'UTF-8') . ';">'
                . (int) $card['value'] . '</span>'
                . '</td>'
                . '<td width="24%" align="right" valign="middle">'
                . self::pdfIcon((string) ($card['icon'] ?? ''), (string) ($card['iconColor'] ?? '#cccccc'), '14pt')
                . '</td>'
                . '</tr></table>'
                . '</td>';
        }

        return $html . '</tr></table><table cellpadding="0" cellspacing="0" width="100%"><tr><td height="8">&nbsp;</td></tr></table>';
    }

    public static function renderGestaoPdfItem($item): string
    {
        $status = self::getAdesaoStatusDisplay($item->st_adesao ?? '');
        $meta   = [self::pdfIcon(self::getEsferaIcon($item)) . ' ' . self::esc($item, 'esfera')];

        if (!empty($item->tp_solicitacao_label)) {
            $meta[] = self::pdfIcon('fas fa-tag') . ' ' . self::esc($item, 'tp_solicitacao_label');
        }

        if (!empty($item->ds_modalidade_label)) {
            $meta[] = self::pdfIcon('fa fa-award') . ' ' . self::esc($item, 'ds_modalidade_label');
        }

        if (!empty($item->dt_criacao_formatado)) {
            $meta[] = self::pdfIcon('far fa-clock') . ' ' . self::esc($item, 'dt_criacao_formatado');
        }

        $docs = '<span style="color:#28a745;"><b>' . (int) ($item->qtRespostaAprovada ?? 0) . '</b>'
            . self::pdfIcon('fas fa-check', '#28a745') . '</span>'
            . ' &nbsp; <span style="color:#f0a500;"><b>' . (int) ($item->qtRespostaPendente ?? 0) . '</b>'
            . self::pdfIcon('fas fa-hourglass-half', '#f0a500') . '</span>'
            . ' &nbsp; <span style="color:#e74c3c;"><b>' . (int) ($item->qtRespostaRejeitada ?? 0) . '</b>X</span>';

        if ((int) ($item->qtRespostaComplementacao ?? 0) > 0) {
            $docs .= ' &nbsp; <span style="color:#1a73e8;"><b>' . (int) $item->qtRespostaComplementacao . '</b>'
                . self::pdfIcon('fas fa-exclamation-circle', '#1a73e8') . '</span>';
        }

        return self::renderPdfItemCard(
            self::esc($item, 'nr_protocolo'),
            $status,
            self::esc($item, 'no_oficial'),
            implode(' &nbsp; · &nbsp; ', $meta),
            [
                self::pdfIcon('far fa-file-alt') . ' Docs:',
                $docs,
            ]
        );
    }

    public static function renderPreenchimentoPdfItem($item): string
    {
        $status = self::getAdesaoStatusDisplay('rascunho');
        $pct    = max(0, min(100, (int) ($item->pc_preenchimento ?? 0)));
        $meta   = [self::pdfIcon(self::getEsferaIcon($item)) . ' ' . self::esc($item, 'esfera')];

        if (!empty($item->ds_email_responsavel)) {
            $meta[] = self::pdfIcon('far fa-envelope') . ' ' . self::esc($item, 'ds_email_responsavel');
        }

        if (!empty($item->dt_atualizacao_formatado)) {
            $meta[] = self::pdfIcon('far fa-clock') . ' Atualizado em ' . self::esc($item, 'dt_atualizacao_formatado');
        }

        $bar = '<table cellpadding="0" cellspacing="0" width="140"><tr><td bgcolor="#eef0f3" height="8">';
        $bar .= $pct > 0
            ? '<table cellpadding="0" cellspacing="0" width="' . $pct . '%"><tr><td bgcolor="#f0a500" height="8">&nbsp;</td></tr></table>'
            : '&nbsp;';
        $bar .= '</td></tr></table>';

        $side = [
            'Preenchimento',
            '<b>' . $pct . '%</b> &nbsp; ' . (int) ($item->qt_preenchidas ?? 0) . ' de ' . (int) ($item->qt_perguntas ?? 0) . ' campos',
            $bar,
        ];

        return self::renderPdfItemCard(
            self::esc($item, 'nr_protocolo'),
            $status,
            self::esc($item, 'no_oficial'),
            implode(' &nbsp; · &nbsp; ', $meta),
            $side
        );
    }

    /**
     * Monta o HTML do Formulário de Adesão preenchido, para anexação ao processo SEI do ente.
     *
     * @param   object  $item        Adesão carregada por AdesaorespostasModel::getItem() (com `respostas`)
     * @param   array   $municipios  Municípios vinculados (MunicipioAdesaoModel::getMunicipiosByAdesao())
     */
    public static function renderFormularioAdesaoHtml($item, array $municipios = []): string
    {
        $e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $naoInformado = '<span class="text-muted">Não informado</span>';
        $valorOuVazio = static fn ($valor) => trim((string) $valor) !== '' ? $e($valor) : $naoInformado;

        $esferas = [
            'estadual'  => 'Estado',
            'municipal' => 'Município',
            'consorcio' => 'Consórcios/Associações de Municípios',
        ];
        $esfera = (string) ($item->tp_esfera_governamental ?? '');

        $solicitante = [
            'Solicitante'  => $e($esferas[$esfera] ?? $esfera),
            'Nome Oficial' => $valorOuVazio($item->no_oficial ?? ''),
            'CNPJ'         => $valorOuVazio($item->ds_cnpj ?? ''),
            'UF'           => $valorOuVazio($item->sg_estado ?? ''),
        ];

        if ($esfera !== 'estadual') {
            if (!empty($municipios)) {
                $linhas = array_map(static function ($municipio) use ($e) {
                    $linha = $e($municipio->no_municipio . ' (' . $municipio->sg_estado . ')');

                    return !empty($municipio->ds_email) ? $linha . ' – ' . $e($municipio->ds_email) : $linha;
                }, $municipios);

                $solicitante[$esfera === 'consorcio' ? 'Municípios integrantes' : 'Município'] = implode('<br/>', $linhas);
            } else {
                $solicitante['Municípios'] = $valorOuVazio($item->ds_municipios ?? '');
            }
        }

        // O CPF do solicitante é o login (username) do usuário que criou a adesão.
        $criador = !empty($item->created_by)
            ? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $item->created_by)
            : null;

        $solicitante['CPF do Solicitante'] = $valorOuVazio($criador->username ?? '');
        $solicitante['E-mail institucional do Responsável'] = $valorOuVazio($item->ds_email_responsavel ?? '');
        $solicitante['E-mail Alternativo'] = $valorOuVazio($item->ds_emails_alternativos ?? '');

        $secoes = self::renderPdfSecao('Identificação do Solicitante', $solicitante);

        $agrupamentoAtual = false;
        $linhas = [];
        $tituloAtual = '';

        foreach ($item->respostas ?? [] as $resposta) {
            if (($resposta->pergunta_tipo ?? '') === 'informativo') {
                continue;
            }

            if ($resposta->co_agrupamento !== $agrupamentoAtual) {
                if ($linhas) {
                    $secoes .= self::renderPdfSecao($tituloAtual, $linhas);
                }

                $agrupamentoAtual = $resposta->co_agrupamento;
                $tituloAtual = $resposta->no_agrupamento ?: 'Outras informações';
                $linhas = [];
            }

            $linhas[] = [$e($resposta->pergunta_titulo ?? ''), RespostaHelper::renderResposta($resposta)];
        }

        if ($linhas) {
            $secoes .= self::renderPdfSecao($tituloAtual, $linhas);
        }

        return self::renderFormularioPdfTemplate(
            $item,
            'FORMULÁRIO DE ADESÃO',
            'Sistema Nacional de Promoção da Igualdade Racial – SINAPIR',
            $secoes
        );
    }

    /**
     * Monta o HTML do Anexo II (estrutura e capacidade de execução orçamentária do órgão) com as
     * respostas do Formulário de Adesão, somente com os campos previstos no Anexo.
     *
     * @param   object  $item  Adesão carregada por AdesaorespostasModel::getItem() (com `respostas`)
     */
    public static function renderFormularioAnexoIIHtml($item): string
    {
        $naoInformado = '<span class="text-muted">Não informado</span>';

        $respostasPorPergunta = [];

        foreach ($item->respostas ?? [] as $resposta) {
            $respostasPorPergunta[(int) $resposta->co_pergunta] = $resposta;
        }

        $secoes = '';

        foreach (self::ANEXO_II_SECOES as $tituloSecao => $itens) {
            $linhas = [];

            foreach ($itens as $rotulo => $perguntas) {
                $valores = [];

                foreach ($perguntas as $idPergunta) {
                    if (isset($respostasPorPergunta[$idPergunta]) && trim((string) $respostasPorPergunta[$idPergunta]->ds_resposta) !== '') {
                        $valores[] = RespostaHelper::renderResposta($respostasPorPergunta[$idPergunta]);
                    }
                }

                $linhas[] = [htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8'), $valores ? implode('<br/>', $valores) : $naoInformado];
            }

            $secoes .= self::renderPdfSecao($tituloSecao, $linhas);
        }

        $secoes .= '<table cellpadding="5" cellspacing="0" width="100%" nobr="true">'
            . '<tr><td>Local e data: ____________________________________________________</td></tr>'
            . '<tr><td height="25">&nbsp;</td></tr>'
            . '<tr><td align="center">______________________________________________________<br/>'
            . 'Identificação e assinatura da autoridade responsável</td></tr>'
            . '</table>';

        return self::renderFormularioPdfTemplate(
            $item,
            'ANEXO II',
            'Informações sobre a estrutura e capacidade de execução orçamentária do órgão de políticas de promoção da igualdade racial',
            $secoes
        );
    }

    /**
     * Preenche o template comum dos PDFs do Formulário de Adesão (cabeçalho, protocolo e rodapé).
     */
    private static function renderFormularioPdfTemplate($item, string $titulo, string $subtitulo, string $secoes): string
    {
        $templatePath = JPATH_SITE . '/components/com_edital/tmpl/pdf/formulario_adesao.html';

        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template PDF não encontrado.');
        }

        $e        = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $logoPath = JPATH_SITE . '/images/logo/logo_comprovante.png';
        $status   = self::getAdesaoStatusDisplay($item->st_adesao ?? '');

        return str_replace(
            ['{{LOGO}}', '{{TITULO}}', '{{SUBTITULO}}', '{{PROTOCOLO}}', '{{STATUS}}', '{{DATA_ENVIO}}', '{{SECOES}}', '{{DATA_GERACAO}}'],
            [
                is_file($logoPath) ? '<img src="' . $logoPath . '" width="110"/>' : '',
                $e($titulo),
                $e($subtitulo),
                $e(self::getProtocoloAdesao($item->id)),
                $e($status['label']),
                !empty($item->dt_criacao)
                    ? $e(HTMLHelper::_('date', $item->dt_criacao, 'd/m/Y \à\s H:i'))
                    : '<span class="text-muted">Não informado</span>',
                $secoes,
                $e(HTMLHelper::_('date', date('Y-m-d H:i:s'), 'd/m/Y \à\s H:i')),
            ],
            file_get_contents($templatePath)
        );
    }

    /**
     * Tabela rótulo/valor de uma seção do PDF. Aceita [rótulo => valor] ou lista de [rótulo, valor];
     * ambos já devem estar escapados.
     */
    private static function renderPdfSecao(string $titulo, array $linhas): string
    {
        $html = '<table class="info-table" cellpadding="5" cellspacing="0" width="100%" nobr="false">'
            . '<tr nobr="true"><td class="section-title" colspan="2">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</td></tr>';

        foreach ($linhas as $rotulo => $valor) {
            if (is_array($valor)) {
                [$rotulo, $valor] = $valor;
            }

            $html .= '<tr nobr="true"><td class="info-label" width="35%">' . $rotulo . '</td>'
                . '<td class="info-value" width="65%">' . $valor . '</td></tr>';
        }

        return $html . '</table><br/><br/>';
    }

    public static function downloadHtmlPdf(string $title, string $html, string $filename, string $orientation = 'L'): void
    {
        require_once JPATH_LIBRARIES . '/tcpdf/tcpdf.php';

        $pdf = new \TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('SINAPIR');
        $pdf->SetAuthor('Sistema SINAPIR');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        self::registerPdfFaFonts($pdf);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    private static function renderPdfItemCard(string $protocolo, array $status, string $nome, string $meta, array $sideAreas): string
    {
        $icon = self::pdfIcon((string) ($status['icon'] ?? ''), $status['color'], '8pt');
        $header = '<span class="adesao-protocolo">' . $protocolo . '</span> &nbsp; '
            . '<span style="background-color:' . $status['bg'] . '; color:' . $status['color'] . ';">&nbsp;'
            . $icon . ' ' . htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8') . '&nbsp;</span>';

        return '<table class="adesao-item" cellpadding="8" cellspacing="0" width="100%" style="page-break-inside:avoid;">'
            . '<tr>'
            . '<td width="72%" valign="top">'
            . self::renderPdfStackedAreas([
                $header,
                '<span class="adesao-nome">' . $nome . '</span>',
                '<span class="adesao-meta">' . $meta . '</span>',
            ])
            . '</td>'
            . '<td class="adesao-side" width="28%" valign="top" style="border-left:1px solid #f0f0f0;">'
            . self::renderPdfStackedAreas($sideAreas)
            . '</td>'
            . '</tr></table><br/>';
    }

    private static function renderPdfStackedAreas(array $areas): string
    {
        $html = '<table cellpadding="0" cellspacing="0" width="100%">';

        foreach (array_values($areas) as $i => $area) {
            if ($i > 0) {
                $html .= '<tr><td height="4" style="font-size:4pt; line-height:4pt;">&nbsp;</td></tr>';
            }

            $html .= '<tr><td>' . $area . '</td></tr>';
        }

        return $html . '</table>';
    }

    private static function esc($item, string $field): string
    {
        if ($field === 'esfera') {
            return htmlspecialchars(self::getEsferaDisplay($item), ENT_QUOTES, 'UTF-8');
        }

        return htmlspecialchars((string) ($item->{$field} ?? ''), ENT_QUOTES, 'UTF-8');
    }

    private static function getEsferaIcon($item): string
    {
        $map = [
            'municipal' => 'fas fa-city',
            'estadual'  => 'fas fa-map-marker-alt',
            'consorcio' => 'fas fa-handshake',
            'federal'   => 'fas fa-flag',
        ];

        return $map[$item->tp_esfera_governamental ?? ''] ?? 'fas fa-building';
    }

    private static function pdfIcon(string $icon, string $color = '#888888', string $size = '8pt'): string
    {
        if ($icon === '' || !preg_match('/fa-([a-z0-9-]+)/', $icon, $match)) {
            return '';
        }

        $glyphs = [
            'chart-bar'          => 'f080',
            'clock'              => 'f017',
            'comment-alt'        => 'f27a',
            'check-circle'       => 'f058',
            'times-circle'       => 'f057',
            'gavel'              => 'f0e3',
            'edit'               => 'f044',
            'city'               => 'f64f',
            'map-marker-alt'     => 'f3c5',
            'handshake'          => 'f2b5',
            'pause-circle'       => 'f28b',
            'file-alt'           => 'f15c',
            'envelope'           => 'f0e0',
            'award'              => 'f559',
            'check'              => 'f00c',
            'hourglass-half'     => 'f252',
            'exclamation-circle' => 'f06a',
            'building'           => 'f1ad',
            'flag'               => 'f024',
            'tag'                => 'f02b',
        ];

        $code = $glyphs[$match[1]] ?? '';

        if ($code === '') {
            return '';
        }

        $regular = (bool) preg_match('/\b(far|fa-regular)\b/', $icon);
        $family  = $regular ? 'fa400' : 'fasolid900';

        return '<span style="font-family:' . $family . '; color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8')
            . '; font-size:' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . ';">&#x' . $code . ';</span>';
    }

    private static function registerPdfFaFonts(\TCPDF $pdf): void
    {
        $dir = JPATH_CACHE . '/com_edital/tcpdf_fonts/';

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $fonts = [
            'fasolid900' => JPATH_SITE . '/media/vendor/fontawesome-free/webfonts/fa-solid-900.ttf',
            'fa400'      => JPATH_SITE . '/media/vendor/fontawesome-free/webfonts/fa-regular-400.ttf',
        ];

        foreach ($fonts as $expected => $ttf) {
            if (!is_file($ttf)) {
                continue;
            }

            $name = \TCPDF_FONTS::addTTFfont($ttf, 'TrueTypeUnicode', '', 32, $dir) ?: $expected;
            $def  = $dir . $name . '.php';

            if (is_file($def)) {
                $pdf->AddFont($name, '', $def);
            }
        }
    }
}
