<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meta\MetaFormRequest;
use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Jobs\SyncMetaFormsJob;
use App\Jobs\SyncMetaLeadsJob;
use App\Models\MetaForm;
use App\Models\MetaPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar los formularios de Meta en la app
 */
class MetaFormController extends Controller
{
    public function index(Request $request)
    {
        $items = MetaForm::query()
            ->with(['page:id,customer_id,name', 'page.customer:id,name'])
            ->withCount('fieldMappings')
            ->when($request->filled('meta_page_id'), fn ($query) => $query->where('meta_page_id', $request->integer('meta_page_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (bool) $request->integer('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('meta_form_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('meta.forms.index', [
            'items' => $items,
            'pages' => MetaPage::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('meta.forms.create', [
            'form' => new MetaForm(['status' => false]),
            'pages' => MetaPage::query()->orderBy('name')->get(['id', 'name', 'customer_id']),
        ]);
    }

    public function store(MetaFormRequest $request): RedirectResponse
    {
        $form = MetaForm::create($request->validated());

        return redirect()
            ->route('meta.forms.show', $form)
            ->with('success', 'Formulario Meta creado correctamente.');
    }

    public function show(MetaForm $form, MetaLeadAdsSyncService $service)
    {
        $form->load([
            'page.customer:id,name',
            'fieldMappings' => fn ($query) => $query->orderBy('id'),
        ]);

        return view('meta.forms.show', [
            'form' => $form,
            'availableMetaFields' => $service->availableMetaFields($form),
        ]);
    }

    public function edit(MetaForm $form)
    {
        return view('meta.forms.edit', [
            'form' => $form,
            'pages' => MetaPage::query()->orderBy('name')->get(['id', 'name', 'customer_id']),
        ]);
    }

    public function update(MetaFormRequest $request, MetaForm $form): RedirectResponse
    {
        $form->update($request->validated());

        return redirect()
            ->route('meta.forms.show', $form)
            ->with('success', 'Formulario Meta actualizado correctamente.');
    }

    public function destroy(MetaForm $form): RedirectResponse
    {
        $form->delete();

        return redirect()
            ->route('meta.forms.index')
            ->with('success', 'Formulario Meta eliminado correctamente.');
    }

    public function syncLeads(MetaForm $form): RedirectResponse
    {
        SyncMetaLeadsJob::dispatch($form->id);

        return back()->with('success', 'Sincronización de leads enviada a la cola.');
    }

    public function syncAll(): RedirectResponse
    {
        SyncMetaFormsJob::dispatch();

        return back()->with('success', 'Sincronización global de formularios enviada a la cola.');
    }
}
