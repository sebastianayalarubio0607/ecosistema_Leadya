<?php

namespace App\Services\Alerts;

use App\Models\Alerts\Alert;
use App\Models\Alerts\AlertComment;
use App\Models\Alerts\AlertRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AlertInteractionService
{
    public function read(int $recipientId, int $userId): void
    {
        $this->change($recipientId, $userId, function ($recipient) use ($userId) {
            if (! $recipient->read_at) {
                $recipient->update(['read_at' => now()]);
                app(AlertIncidentService::class)->event($recipient->alert, 'read', [], null, $userId);
            }
        });
    }

    public function acknowledge(int $recipientId, int $userId, string $comment): void
    {
        $comment = trim($comment);
        if ($comment === '' || mb_strlen($comment) > 4000) {
            throw ValidationException::withMessages(['comment' => 'Escribe un comentario de entre 1 y 4000 caracteres.']);
        }
        $this->change($recipientId, $userId, function ($recipient) use ($userId, $comment) {
            if ($recipient->acknowledged_at) {
                return;
            }
            AlertComment::create(['recipient_id' => $recipient->id, 'user_id' => $userId, 'body' => $comment]);
            $recipient->update(['read_at' => $recipient->read_at ?? now(), 'acknowledged_at' => now(), 'dismissed_at' => now()]);
            app(AlertIncidentService::class)->event($recipient->alert, 'acknowledged', [], null, $userId);
        });
    }

    public function dismiss(int $recipientId, int $userId): void
    {
        $this->change($recipientId, $userId, function ($recipient) {
            if ($recipient->requires_response && ! $recipient->acknowledged_at) {
                throw ValidationException::withMessages(['comment' => 'Esta alerta requiere un comentario para confirmar que te enteraste.']);
            }
            $recipient->update(['read_at' => $recipient->read_at ?? now(), 'dismissed_at' => now()]);
        });
    }

    public function manage(int $alertId, string $status, string $reason): void
    {
        Gate::authorize('manage-alerts');
        if (! in_array($status, ['in_progress', 'ignored', 'open'], true) || trim($reason) === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Selecciona un estado y escribe un motivo de hasta 2000 caracteres.']);
        }
        DB::transaction(function () use ($alertId, $status, $reason) {
            $alert = Alert::whereKey($alertId)->lockForUpdate()->firstOrFail();
            abort_if($alert->resolved_at !== null, 422, 'Una incidencia resuelta conserva su historial.');
            abort_if(isset($alert->metadata['metric_subscription_id']) && ! $alert->active_key, 422, 'Esta evaluación conserva su historial; modifica la configuración para iniciar otra.');
            $alert->update(['status' => $status]); // Keep active_key until physical recovery.
            app(AlertIncidentService::class)->event($alert, 'status_changed', ['status' => $status, 'reason' => trim($reason)], null, auth()->id());
        }, 3);
    }

    private function change(int $id, int $userId, callable $action): void
    {
        DB::transaction(function () use ($id, $userId, $action) {
            $recipient = AlertRecipient::whereKey($id)->where('user_id', $userId)->where('notification_count', '>', 0)->lockForUpdate()->firstOrFail();
            $action($recipient);
        }, 3);
    }
}
