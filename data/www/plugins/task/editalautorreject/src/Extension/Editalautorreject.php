<?php

/**
 * @package     Joomla.Plugins
 * @subpackage  Task.Editalautorreject
 *
 * @copyright   2026 SINAPIR
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\Editalautorreject\Extension;

\defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Model\AdesaoModel;
use Comedital\Component\Edital\Site\Service\AdesaoEmailService;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

final class Editalautorreject extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    protected const TASKS_MAP = [
        'editalautorreject.reject_overdue_complementacao' => [
            'langConstPrefix' => 'PLG_TASK_EDITALAUTORREJECT_TASK_REJECT_OVERDUE',
            'form'            => 'reject_overdue',
            'method'          => 'rejectOverdueComplementacao',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }
        
    protected function rejectOverdueComplementacao(ExecuteTaskEvent $event): int
    {

        $this->logTask('Iniciando rejeição automática de respostas em complementação vencidas.', 'info');
        $params = $event->getArgument('params');
        $reason = trim((string) ($params->reason ?? 'Prazo de complementacao expirado automaticamente pelo sistema.'));
        $recursoDays = max(1, (int) ($params->recurso_days ?? 30));
        $batchSize = max(1, (int) ($params->batch_size ?? 200));
        $updatedBy = max(0, (int) ($params->updated_by ?? 0));

        $db = $this->getDatabase();
        $tzOffset = Factory::getConfig()->get('offset');
        $nowSql = Factory::getDate('now', $tzOffset)->format('Y-m-d H:i:s', true);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('co_adesao')])
            ->from($db->quoteName('#__tb_resposta'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('st_situacao') . ' = ' . $db->quote('complementacao'))
            ->where($db->quoteName('dt_limite') . ' IS NOT NULL')
            ->where($db->quoteName('dt_limite') . ' < ' . $db->quote($nowSql))
            ->order($db->quoteName('dt_limite') . ' ASC');
        $db->setQuery($query, 0, $batchSize);
        $expiredRows = (array) $db->loadObjectList();

        if (empty($expiredRows)) {
            $this->logTask('Nenhuma resposta em complementacao vencida foi encontrada.', 'info');

            return TaskStatus::OK;
        }

        $responseIds = [];
        $adesaoIds = [];

        foreach ($expiredRows as $row) {
            $responseIds[] = (int) $row->id;

            if ((int) $row->co_adesao > 0) {
                $adesaoIds[] = (int) $row->co_adesao;
            }
        }

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__tb_resposta'))
            ->set($db->quoteName('st_situacao') . ' = ' . $db->quote('rejeitado'))
            ->set($db->quoteName('ds_comentario_avaliador') . ' = ' . $db->quote($reason))
            ->set($db->quoteName('dt_alterado') . ' = ' . $db->quote($nowSql))
            ->set($db->quoteName('modified_by') . ' = ' . $db->quote($updatedBy))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $responseIds) . ')');
        $db->setQuery($update);
        $db->execute();

        $indeferidas = 0;
        foreach (array_values(array_unique($adesaoIds)) as $adesaoId) {
            if ($this->tryIndeferirAdesao($adesaoId, $reason, $recursoDays, $updatedBy, $nowSql)) {
                $indeferidas++;
            }
        }

        $this->logTask(
            sprintf(
                'Respostas rejeitadas automaticamente: %d. Adesoes indeferidas: %d.',
                count($responseIds),
                $indeferidas
            ),
            'info'
        );

        return TaskStatus::OK;
    }

    private function tryIndeferirAdesao(int $adesaoId, string $reason, int $recursoDays, int $updatedBy, string $nowSql): bool
    {
        $db = $this->getDatabase();

        $statusQuery = $db->getQuery(true)
            ->select([
                'SUM(CASE WHEN ' . $db->quoteName('st_situacao') . ' = ' . $db->quote('pendente') . ' THEN 1 ELSE 0 END) AS qt_pendentes',
                'SUM(CASE WHEN ' . $db->quoteName('st_situacao') . ' = ' . $db->quote('complementacao') . ' THEN 1 ELSE 0 END) AS qt_complementacao',
                'SUM(CASE WHEN ' . $db->quoteName('st_situacao') . ' = ' . $db->quote('rejeitado') . ' THEN 1 ELSE 0 END) AS qt_rejeitadas',
            ])
            ->from($db->quoteName('#__tb_resposta', 'r'))
            ->innerJoin($db->quoteName('#__tb_pergunta', 'p'), $db->quoteName('r.co_pergunta') . ' = ' . $db->quoteName('p.id'))
            ->where($db->quoteName('r.state') . ' = 1')
            ->where($db->quoteName('p.tp_pergunta') . ' <> '. $db->quote('informativo'))
            ->where($db->quoteName('p.state') . ' =  1')
            ->where($db->quoteName('r.co_adesao') . ' = ' . $db->quote($adesaoId));
        $db->setQuery($statusQuery);
        $status = $db->loadObject();

        if (!$status) {
            return false;
        }

        if ((int) $status->qt_pendentes > 0 || (int) $status->qt_complementacao > 0 || (int) $status->qt_rejeitadas === 0) {
            return false;
        }

        $adesaoQuery = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('st_adesao')])
            ->from($db->quoteName('#__tb_adesao'))
            ->where($db->quoteName('id') . ' = ' . $db->quote($adesaoId));
        $db->setQuery($adesaoQuery);
        $adesao = $db->loadObject();

        if (!$adesao || in_array( $adesao->st_adesao, ['indeferido', 'deferido'])) {
            return false;
        }

        $limiteRecurso = Factory::getDate()->add(new \DateInterval('P' . $recursoDays . 'D'))->toSql();
        $adesaoModel = new AdesaoModel();

        $updated = (bool) $adesaoModel->alterarAdesao($adesaoId, [
            'st_adesao' => 'indeferido',
            'ds_resposta' => $reason,
            'dt_limite_situacao' => $limiteRecurso,
            'dt_alterado' => $nowSql,
            'modified_by' => $updatedBy,
        ]);

        if (!$updated) {
            return false;
        }

        $adesaoModel->inserirMarcoTemporal($adesaoId, 'indeferido', $updatedBy);
        $this->sendIndeferidoEmail($adesaoId, $reason);

        return true;
    }

    private function sendIndeferidoEmail(int $adesaoId, string $reason): void
    {
        try {
            $emailService = new AdesaoEmailService();
            $emailService->enviarEmailEvento(
                $adesaoId,
                'adesao_indeferida.html',
                'Adesão Indeferida',
                [
                    '{{STATUS}}' => 'Indeferido',
                    '{{MOTIVO}}' => nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')),
                ]
            );
        } catch (\Throwable $e) {
            $this->logTask('Falha ao enviar email de indeferimento para adesao ' . $adesaoId . ': ' . $e->getMessage(), 'warning');
        }
    }
}