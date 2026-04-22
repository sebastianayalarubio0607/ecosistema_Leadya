<?php

namespace App\Jobs;

use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Models\MetaAccessToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Trabajo para refrescar los tokens de larga duración de Meta que estén próximos a expirar.
 * Este trabajo puede ser programado para ejecutarse periódicamente (por ejemplo, diariamente) y se encarga de identificar los tokens que necesitan ser refrescados y realizar el proceso de refresco utilizando el MetaLeadAdsSyncService.
 * Si se proporciona un token específico, solo se intentará refrescar ese token, lo que permite una ejecución más focalizada en caso de que se detecte que un token específico está próximo a expirar.
 * El trabajo maneja excepciones que puedan ocurrer durante el proceso de refresco, registrando los errores en los logs para su revisión y asegur   ando que los problemas con los tokens sean visibles y puedan ser atendidos por el equipo de soporte o desarrollo.
 * Se configuran reintentos y backoff para manejar posibles fallos temporales en el proceso de refresco, y se asigna el trabajo a una cola específica para tareas relacionadas con Meta, lo que ayuda a organizar y priorizar las tareas en el sistema de colas.        
 * 
 */
class RefreshMetaLongLivedTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public ?int $metaAccessTokenId = null,
    ) {
        $this->onQueue('meta');
    }

    public function handle(MetaLeadAdsSyncService $service): void
    {
        $token = $this->metaAccessTokenId ? MetaAccessToken::find($this->metaAccessTokenId) : null;
        $service->refreshDueTokens($token);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RefreshMetaLongLivedTokenJob failed', [
            'meta_access_token_id' => $this->metaAccessTokenId,
            'message' => $exception->getMessage(),
        ]);
    }
}
