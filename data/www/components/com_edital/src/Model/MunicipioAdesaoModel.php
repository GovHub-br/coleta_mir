<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
use \Joomla\Database\DatabaseDriver;
use \Joomla\Database\ParameterType;

/**
 * Model responsável por associar municípios (e, quando aplicável, o e-mail de
 * contato de cada um) a uma adesão. Utilizado quando o solicitante é do tipo
 * Município (um único registro, sem e-mail) ou Consórcio de Municípios
 * (um registro por município integrante, com e-mail).
 *
 * @since  1.0.0
 */
class MunicipioAdesaoModel extends BaseDatabaseModel
{
	/**
	 * Retorna os municípios (e respectivos e-mails, quando houver) vinculados a uma adesão.
	 *
	 * @param   int  $adesaoId  Id da adesão
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getMunicipiosByAdesao($adesaoId)
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(['ma.id', 'ma.co_adesao', 'ma.co_ibge', 'ma.ds_email', 'm.no_municipio', 'e.sg_estado']))
			->from($db->quoteName('#__tb_municipio_adesao') . ' AS ma')
			->innerJoin(
				$db->quoteName('#__tb_municipio') . ' AS m',
				$db->quoteName('m.co_ibge') . ' = ' . $db->quoteName('ma.co_ibge')
			)
			->innerJoin(
				$db->quoteName('#__tb_estado') . ' AS e', 
				$db->quoteName('e.co_estado') . ' = ' . $db->quoteName('m.co_estado')
			)
			->where($db->quoteName('ma.co_adesao') . ' = :coAdesao')
			->bind(':coAdesao', $adesaoId, ParameterType::INTEGER);

		$db->setQuery($query);

		return $db->loadObjectList();
	}

	/**
	 * Substitui os municípios vinculados a uma adesão pela lista informada.
	 *
	 * Remove todos os registros existentes para a adesão e insere novamente,
	 * garantindo que a tabela reflita exatamente o que foi enviado no formulário.
	 *
	 * @param   int              $adesaoId    Id da adesão
	 * @param   array            $municipios  Lista de municípios no formato
	 *                                        [['co_ibge' => int, 'ds_email' => string|null], ...]
	 * @param   DatabaseDriver   $db          Conexão de banco a utilizar (permite reaproveitar transação)
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function saveMunicipiosAdesao($adesaoId, array $municipios, ?DatabaseDriver $db = null)
	{
		if ($db === null) {
			$db = Factory::getContainer()->get('DatabaseDriver');
		}

		$adesaoId = (int) $adesaoId;

		// Remove os vínculos anteriores da adesão (ex.: reenvio de rascunho).
		$deleteQuery = $db->getQuery(true)
			->delete($db->quoteName('#__tb_municipio_adesao'))
			->where($db->quoteName('co_adesao') . ' = :coAdesao')
			->bind(':coAdesao', $adesaoId, ParameterType::INTEGER);

		$db->setQuery($deleteQuery);
		$db->execute();

		if (empty($municipios)) {
			return true;
		}

		foreach ($municipios as $municipio) {
			$coIbge = (int) ($municipio['co_ibge'] ?? 0);

			if ($coIbge <= 0) {
				continue;
			}

			$dsEmail = isset($municipio['ds_email']) && $municipio['ds_email'] !== ''
				? (string) $municipio['ds_email']
				: null;

			$insertQuery = $db->getQuery(true)
				->insert($db->quoteName('#__tb_municipio_adesao'))
				->columns($db->quoteName(['co_adesao', 'co_ibge', 'ds_email']))
				->values(':coAdesao, :coIbge, :dsEmail')
				->bind(':coAdesao', $adesaoId, ParameterType::INTEGER)
				->bind(':coIbge', $coIbge, ParameterType::INTEGER)
				->bind(':dsEmail', $dsEmail, $dsEmail === null ? ParameterType::NULL : ParameterType::STRING);

			$db->setQuery($insertQuery);
			$db->execute();
		}

		return true;
	}
}
