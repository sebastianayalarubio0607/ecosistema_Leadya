<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UrlGeneratorController extends Controller
{
    public function showForm()
    {
        // El formato es 'abreviatura' => 'Nombre Completo'
        $options = [
            'origin' => [
                'gads' => 'Google Ads', 'yt' => 'YouTube Ads', 'gm' => 'Google Maps', 
                'gs' => 'Google Shopping', 'fb' => 'Facebook', 'ig' => 'Instagram', 
                'fbm' => 'Messenger', 'wa' => 'WhatsApp'
            ],
            'platform' => [
                'search' => 'Search', 'display' => 'Display', 'video' => 'Video', 
                'apps' => 'Apps', 'discovery' => 'Discovery', 'max' => 'Performance Max', 
                'demand' => 'Demand Gen', 'reels' => 'Reels', 'shorts' => 'Shorts'
            ],
            'geo' => [

                'latam' => 'Latam',
                'eu' => 'Europa',

                'bog' => 'Bogota',
                'clo' => 'Cali',
                'baq' => 'Barranquilla',
                'med' => 'Medellin',

                'mex' => 'Mexico DF',
                'us' => 'USA',
                'co'=>'Colombia',
                
            ],
            'language' => [
                'es' => 'Español', 'en' => 'Inglés', 'pt' => 'Portugués'
            ]
        ];

        return view('generate-url', compact('options'));
    }
}