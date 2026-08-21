<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meta\MetaPageRequest;
use App\Http\Services\Meta\MetaAssetStatusSyncService;
use App\Jobs\SyncMetaAssetStatusesJob;
use App\Jobs\SyncMetaFormsJob;
use App\Jobs\SyncMetaPagesJob;
use App\Jobs\SyncMetaPageStatusJob;
use App\Models\Customer;
use App\Models\MetaPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar las páginas de Meta en la app
 */
class MetaPageController extends Controller
{
    public function index(Request $request)
    {
        $items = MetaPage::query()
            ->with(['customer:id,name'])
            ->withCount('forms')
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('meta_page_id'), fn ($query) => $query->where('meta_page_id', 'like', '%'.$request->string('meta_page_id')->toString().'%'))
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%'.$request->string('name')->toString().'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (bool) $request->integer('status')))
            ->when($request->filled('estado_meta'), fn ($query) => $query->where('estado_meta', $request->string('estado_meta')->toString()))
            ->when($request->filled('leadgen'), fn ($query) => $query->where('is_leadgen_subscribed', (bool) $request->integer('leadgen')))
            ->when($request->filled('forms'), function ($query) use ($request) {
                $formsFilter = $request->string('forms')->toString();

                if ($formsFilter === 'with') {
                    $query->has('forms');
                }

                if ($formsFilter === 'without') {
                    $query->doesntHave('forms');
                }
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('meta_page_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('meta.pages.index', [
            'items' => $items,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('meta.pages.create', [
            'page' => new MetaPage(['status' => false]),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(MetaPageRequest $request): RedirectResponse
    {
        $page = MetaPage::create($request->validated());

        return redirect()
            ->route('meta.pages.show', $page)
            ->with('success', 'Página Meta creada correctamente.');
    }

    public function show(MetaPage $page)
    {
        $page->load([
            'customer:id,name',
            'forms' => fn ($query) => $query->orderBy('name'),
            'statusHistories' => fn ($query) => $query->with('webhookEvent:id,object,field')->orderByDesc('consulted_at')->limit(15),
        ]);

        return view('meta.pages.show', compact('page'));
    }

    public function edit(MetaPage $page)
    {
        return view('meta.pages.edit', [
            'page' => $page,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(MetaPageRequest $request, MetaPage $page): RedirectResponse
    {
        $page->update($request->validated());

        return redirect()
            ->route('meta.pages.show', $page)
            ->with('success', 'Página Meta actualizada correctamente.');
    }

    public function destroy(MetaPage $page): RedirectResponse
    {
        $page->delete();

        return redirect()
            ->route('meta.pages.index')
            ->with('success', 'Página Meta eliminada correctamente.');
    }

    public function syncForms(MetaPage $page): RedirectResponse
    {
        SyncMetaFormsJob::dispatch($page->id);

        return back()->with('success', 'Sincronización de formularios enviada a la cola.');
    }

    public function syncAll(): RedirectResponse
    {
        SyncMetaPagesJob::dispatch();

        return back()->with('success', 'Sincronización global de páginas enviada a la cola.');
    }

    public function syncStatuses(): RedirectResponse
    {
        SyncMetaAssetStatusesJob::dispatch('manual', MetaAssetStatusSyncService::ASSET_TYPE_PAGES);

        return back()->with('success', 'Consulta de estados de paginas Meta enviada a la cola.');
    }

    public function syncStatus(MetaPage $page): RedirectResponse
    {
        SyncMetaPageStatusJob::dispatch((int) $page->id, 'manual');

        return back()->with('success', 'Consulta de estado de la pagina Meta enviada a la cola.');
    }
}
