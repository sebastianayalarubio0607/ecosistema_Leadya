<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meta\MetaFormFieldMappingRequest;
use App\Http\Services\Meta\MetaLeadAdsSyncService;
use App\Models\Lead;
use App\Models\MetaForm;
use App\Models\MetaFormFieldMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar los mappings de campos entre formularios de Meta y campos de Lead en la app
 */
class MetaFormFieldMappingController extends Controller
{
    public function index(Request $request)
    {
        $items = MetaFormFieldMapping::query()
            ->with(['form.page.customer'])
            ->when($request->filled('meta_form_id'), fn ($query) => $query->where('meta_form_id', $request->integer('meta_form_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('meta_field_name', 'like', "%{$search}%")
                        ->orWhere('lead_field_name', 'like', "%{$search}%")
                        ->orWhere('static_value', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('meta.form_field_mappings.index', [
            'items' => $items,
            'forms' => MetaForm::query()->orderBy('name')->get(['id', 'name', 'meta_form_id']),
        ]);
    }

    public function create(Request $request, MetaLeadAdsSyncService $service)
    {
        $selectedForm = $request->filled('meta_form_id')
            ? MetaForm::query()->find($request->integer('meta_form_id'))
            : null;

        return view('meta.form_field_mappings.create', [
            'mapping' => new MetaFormFieldMapping([
                'meta_form_id' => $selectedForm?->id,
                'is_required' => false,
                'is_active' => true,
            ]),
            'forms' => MetaForm::query()->orderBy('name')->get(['id', 'name', 'meta_form_id']),
            'leadFields' => Lead::metaMappableFields(),
            'availableMetaFields' => $selectedForm ? $service->availableMetaFields($selectedForm) : [],
        ]);
    }

    public function store(MetaFormFieldMappingRequest $request): RedirectResponse
    {
        $mapping = MetaFormFieldMapping::create($request->validated());

        return redirect()
            ->route('meta.form-field-mappings.show', $mapping)
            ->with('success', 'Mapping de campos creado correctamente.');
    }

    public function show(MetaFormFieldMapping $mapping, MetaLeadAdsSyncService $service)
    {
        $mapping->load('form.page.customer');

        return view('meta.form_field_mappings.show', [
            'mapping' => $mapping,
            'availableMetaFields' => $service->availableMetaFields($mapping->form),
        ]);
    }

    public function edit(MetaFormFieldMapping $mapping, MetaLeadAdsSyncService $service)
    {
        $mapping->load('form');

        return view('meta.form_field_mappings.edit', [
            'mapping' => $mapping,
            'forms' => MetaForm::query()->orderBy('name')->get(['id', 'name', 'meta_form_id']),
            'leadFields' => Lead::metaMappableFields(),
            'availableMetaFields' => $service->availableMetaFields($mapping->form),
        ]);
    }

    public function update(MetaFormFieldMappingRequest $request, MetaFormFieldMapping $mapping): RedirectResponse
    {
        $mapping->update($request->validated());

        return redirect()
            ->route('meta.form-field-mappings.show', $mapping)
            ->with('success', 'Mapping de campos actualizado correctamente.');
    }

    public function destroy(MetaFormFieldMapping $mapping): RedirectResponse
    {
        $mapping->delete();

        return redirect()
            ->route('meta.form-field-mappings.index')
            ->with('success', 'Mapping de campos eliminado correctamente.');
    }
}
