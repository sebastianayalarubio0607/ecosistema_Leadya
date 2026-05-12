<?php

namespace App\Http\Services;

use App\Models\Source;

class SourceService
{
    public function list(?string $q = null)
    {
        return Source::query()
            ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }

    public function store(array $data): Source
    {
        return Source::create($data);
    }

    public function update(Source $source, array $data): Source
    {
        $source->update($data);

        return $source;
    }

    public function destroy(Source $source): void
    {
        $source->delete();
    }
}
