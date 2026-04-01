<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\MondayBoardRequest;
use App\Http\Services\Integration\MondayIntegrationService;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\MondayBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MondayBoardController extends Controller
{
    public function edit(Integration $integration, MondayBoard $board)
    {
        $this->ensureMondayBoard($integration, $board);

        $board->load(['groups', 'columns.mapping']);

        return view('integrations.monday.boards.edit', [
            'integration' => $integration,
            'board' => $board,
            'leadFields' => Lead::integrationMappableFields(),
        ]);
    }

    public function syncBoards(Integration $integration, MondayIntegrationService $service): RedirectResponse
    {
        $this->ensureMondayIntegration($integration);

        $service->syncBoards($integration);

        return redirect()
            ->route('integrations.show', $integration)
            ->with('success', 'Boards de Monday sincronizados correctamente.');
    }

    public function syncDetails(Integration $integration, MondayBoard $board, MondayIntegrationService $service): RedirectResponse
    {
        $this->ensureMondayBoard($integration, $board);

        $service->syncBoardDetails($board);

        return redirect()
            ->route('integrations.monday.boards.edit', [$integration, $board])
            ->with('success', 'Grupos y columnas sincronizados correctamente.');
    }

    public function update(MondayBoardRequest $request, Integration $integration, MondayBoard $board, MondayIntegrationService $service): RedirectResponse
    {
        $this->ensureMondayBoard($integration, $board);

        $service->updateBoardConfiguration($board, $request->validated());

        return redirect()
            ->route('integrations.monday.boards.edit', [$integration, $board])
            ->with('success', 'Configuracion del board actualizada correctamente.');
    }

    private function ensureMondayBoard(Integration $integration, MondayBoard $board): void
    {
        $this->ensureMondayIntegration($integration);

        if ((int) $board->integration_id !== (int) $integration->id) {
            throw new NotFoundHttpException();
        }
    }

    private function ensureMondayIntegration(Integration $integration): void
    {
        $typeName = Str::of((string) optional($integration->integrationtype)->name)
            ->ascii()
            ->lower()
            ->trim()
            ->toString();

        if ($typeName !== 'monday') {
            throw new NotFoundHttpException();
        }
    }
}
