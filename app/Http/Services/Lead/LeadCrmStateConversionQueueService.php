<?php

namespace App\Http\Services\Lead;

use App\Models\LeadCrmStateConversionSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadCrmStateConversionQueueService
{
    public const ACTIVE_QUEUE = 'tracking';
    public const WAITING_QUEUE = 'tracking';
    private const WAITING_AVAILABLE_AT = 2147483647;

    /**
     * Stores every conversion as its own database queue row while activating
     * only the first one. Subsequent rows stay on tracking with a future
     * available_at and are released in order.
     */
    public function dispatchInOrder(int $leadId, array $jobs): ?LeadCrmStateConversionSequence
    {
        if ($jobs === []) {
            return null;
        }

        if (config('queue.connections.'.config('queue.default').'.driver') !== 'database') {
            throw new \LogicException('La secuencia visible requiere que la conexión de cola sea database.');
        }

        $sequence = LeadCrmStateConversionSequence::query()->create([
            'lead_id' => $leadId,
            'current_position' => 0,
            'total_jobs' => count($jobs),
            'status' => 'active',
        ]);

        $dispatched = 0;

        try {
            foreach (array_values($jobs) as $position => $job) {
                if (! method_exists($job, 'setLeadCrmStateSequence')) {
                    throw new \LogicException('El trabajo de conversión no admite secuenciación individual.');
                }

                $job->setLeadCrmStateSequence((int) $sequence->id, $position);
                $job->onQueue(self::ACTIVE_QUEUE);
                if ($position > 0) {
                    $job->delay(now()->setTimestamp(self::WAITING_AVAILABLE_AT));
                }
                dispatch($job);
                $dispatched++;
            }

            return $sequence;
        } catch (Throwable $exception) {
            $sequence->forceFill([
                'total_jobs' => $dispatched,
                'status' => $dispatched > 0 ? 'active' : 'failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            if ($dispatched > 0) {
                Log::error('La secuencia de conversiones se encolo parcialmente.', [
                    'sequence_id' => $sequence->id,
                    'lead_id' => $leadId,
                    'dispatched_jobs' => $dispatched,
                    'message' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    public function completePosition(?int $sequenceId, ?int $position): void
    {
        if (! $sequenceId || $position === null) {
            return;
        }

        $sequence = LeadCrmStateConversionSequence::query()->find($sequenceId);
        if (! $sequence || $sequence->status !== 'active' || (int) $sequence->current_position !== $position) {
            return;
        }

        if ($position + 1 >= (int) $sequence->total_jobs) {
            $sequence->delete();

            return;
        }

        $nextPosition = $position + 1;
        $nextQueueRow = $this->findWaitingQueueRow($sequenceId, $nextPosition);

        if (! $nextQueueRow) {
            $sequence->forceFill([
                'status' => 'failed',
                'error_message' => "No se encontró la fila de cola para la posición {$nextPosition}.",
            ])->save();

            Log::error('No se encontró el siguiente job de una secuencia de conversiones.', [
                'sequence_id' => $sequenceId,
                'lead_id' => $sequence->lead_id,
                'position' => $nextPosition,
            ]);

            return;
        }

        DB::transaction(function () use ($sequence, $nextPosition, $nextQueueRow): void {
            $lockedSequence = LeadCrmStateConversionSequence::query()
                ->lockForUpdate()
                ->find($sequence->id);

            if (! $lockedSequence
                || $lockedSequence->status !== 'active'
                || (int) $lockedSequence->current_position !== $nextPosition - 1) {
                return;
            }

            $this->queueConnection()->table($this->queueTable())
                ->where('id', $nextQueueRow->id)
                ->where('queue', self::ACTIVE_QUEUE)
                ->update(['available_at' => now()->timestamp]);

            $lockedSequence->forceFill(['current_position' => $nextPosition])->save();
        });
    }

    private function findWaitingQueueRow(int $sequenceId, int $position): ?object
    {
        $rows = $this->queueConnection()->table($this->queueTable())
            ->where('queue', self::ACTIVE_QUEUE)
            ->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.command')) LIKE ?",
                ['%leadCrmStateConversionSequenceId";i:'.$sequenceId.';%']
            )
            ->get(['id', 'payload']);

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true);
            $command = data_get($payload, 'data.command');
            if (! is_string($command)) {
                continue;
            }

            if (preg_match('/leadCrmStateConversionSequenceId";i:(\d+);/', $command, $sequenceMatch)
                && preg_match('/leadCrmStateConversionPosition";i:(\d+);/', $command, $positionMatch)
                && (int) $sequenceMatch[1] === $sequenceId
                && (int) $positionMatch[1] === $position) {
                return $row;
            }
        }

        return null;
    }

    private function queueConnection()
    {
        $connectionName = config('queue.connections.database.connection') ?: config('database.default');

        return DB::connection($connectionName);
    }

    private function queueTable(): string
    {
        return (string) config('queue.connections.database.table', 'jobs');
    }
}
