<?php

namespace App\Console\Commands;

use App\Http\Services\Meta\MetaGraphService;
use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Models\MetaAccessToken;
use App\Models\MetaPage;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

class MetaDebugSyncForms extends Command
{
    protected $signature = 'meta:debug-sync-forms
        {meta_page_id : Meta Page ID externo, ej: 97795233251}
        {--token= : Token opcional para comparar/probar sin usar el guardado en BD}
        {--use-option-token : Ejecuta la consulta con --token en vez del token guardado en BD}
        {--run-service : Ejecuta tambien MetaLeadAdsSyncService::syncForms() sobre la pagina}';

    protected $description = 'Diagnostica la sincronizacion de formularios Lead Ads para una pagina Meta.';

    public function handle(MetaGraphService $graphService, MetaLeadAdsSyncService $syncService): int
    {
        $metaPageId = (string) $this->argument('meta_page_id');

        $page = MetaPage::query()
            ->with('customer:id,name')
            ->where('meta_page_id', $metaPageId)
            ->first();

        if (! $page) {
            $this->error("No existe una pagina en meta_pages con meta_page_id={$metaPageId}.");
            return self::FAILURE;
        }

        $dbToken = (string) $page->page_access_token;
        $optionToken = (string) ($this->option('token') ?: '');
        $tokenForRequest = $this->option('use-option-token') ? $optionToken : $dbToken;

        $this->info('Pagina en BD');
        $this->line('id interno: '.$page->id);
        $this->line('meta_page_id: '.$page->meta_page_id);
        $this->line('nombre: '.$page->name);
        $this->line('cliente: '.($page->customer?->name ?: 'SIN CLIENTE'));
        $this->line('status: '.($page->status ? 'Activa' : 'Inactiva'));
        $this->line('last_synced_at: '.($page->last_synced_at?->toDateTimeString() ?: 'NULL'));
        $this->line('last_error: '.($page->last_error ?: 'NULL'));

        $this->newLine();
        $this->info('Token guardado en meta_pages.page_access_token');
        $this->line($this->describeToken($dbToken));

        $this->newLine();
        $this->info('MetaAccessToken activos en BD');
        $this->printActiveAccessTokens();

        if ($optionToken !== '') {
            $this->newLine();
            $this->info('Token recibido por --token');
            $this->line($this->describeToken($optionToken));
            $this->line('Coincide con BD: '.($this->sameToken($dbToken, $optionToken) ? 'SI' : 'NO'));
        }

        if ($tokenForRequest === '') {
            $this->error('No hay token para ejecutar la consulta.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Consulta simulada');
        $this->line('GET https://graph.facebook.com/'.trim((string) config('services.meta.graph_version', 'v24.0'), '/').'/'.$page->meta_page_id.'/leadgen_forms');
        $this->line('fields=id,name,status,locale,questions');
        $this->line('limit=100');
        $this->line('token_source: '.($this->option('use-option-token') ? '--token' : 'BD'));

        try {
            $forms = $graphService->paginatedGet($page->meta_page_id.'/leadgen_forms', [
                'fields' => 'id,name,status,locale,questions',
                'access_token' => $tokenForRequest,
                'limit' => 100,
            ]);

            $this->newLine();
            $this->info('Meta respondio OK');
            $this->line('formularios_recibidos: '.count($forms));

            collect($forms)->take(5)->each(function (array $form): void {
                $this->line('- '.($form['id'] ?? 'sin-id').' | '.($form['name'] ?? 'sin-nombre').' | '.($form['status'] ?? 'sin-status'));
            });
        } catch (RequestException $exception) {
            $this->newLine();
            $this->error('Meta respondio ERROR');
            $this->line('message: '.$exception->getMessage());
            $this->line('body: '.Str::limit((string) $exception->response?->body(), 1200));

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->error('Error ejecutando consulta');
            $this->line('message: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('run-service')) {
            $this->newLine();
            $this->info('Ejecutando MetaLeadAdsSyncService::syncForms()');
            $result = $syncService->syncForms($page->fresh());
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $page->refresh();
            $this->line('last_error despues del servicio: '.($page->last_error ?: 'NULL'));
        }

        return self::SUCCESS;
    }

    private function describeToken(string $token): string
    {
        if ($token === '') {
            return 'VACIO';
        }

        return 'len='.strlen($token)
            .' sha256='.hash('sha256', $token)
            .' inicio='.substr($token, 0, 12)
            .' fin='.substr($token, -12);
    }

    private function sameToken(string $left, string $right): bool
    {
        return hash_equals(hash('sha256', $left), hash('sha256', $right));
    }

    private function printActiveAccessTokens(): void
    {
        $tokens = MetaAccessToken::query()
            ->select(MetaAccessToken::SYNC_COLUMNS)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        if ($tokens->isEmpty()) {
            $this->line('No hay tokens activos en meta_access_tokens.');
            return;
        }

        foreach ($tokens as $token) {
            $metaAppId = (string) ($token->meta_app_id ?: '');
            $this->line('- id='.$token->id
                .' type='.$token->token_type
                .' meta_app_id='.($metaAppId !== '' ? $metaAppId : 'NULL')
                .' working_token='.$this->describeToken((string) $token->working_token)
                .' expires_at='.($token->expires_at?->toDateTimeString() ?: 'NULL')
                .' last_error='.($token->last_error ? Str::limit($token->last_error, 120) : 'NULL'));
        }
    }
}
