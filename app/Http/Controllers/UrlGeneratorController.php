<?php

namespace App\Http\Controllers;

use App\Models\Geo;
use App\Models\Language;
use App\Models\Origin;
use App\Models\Platform;

class UrlGeneratorController extends Controller
{
    public function showForm()
    {
        $options = [
            'origin' => Origin::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray(),
            'platform' => Platform::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray(),
            'geo' => Geo::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray(),
            'language' => Language::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray(),
        ];

        return view('generate-url', compact('options'));
    }
}
