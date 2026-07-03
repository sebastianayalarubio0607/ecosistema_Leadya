<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['name' => 'Peso Colombiano', 'code' => 'COP'],
            ['name' => 'Dolar Estadounidense', 'code' => 'USD'],
            ['name' => 'Euro', 'code' => 'EUR'],
            ['name' => 'Peso Mexicano', 'code' => 'MXN'],
            ['name' => 'Peso Argentino', 'code' => 'ARS'],
            ['name' => 'Peso Chileno', 'code' => 'CLP'],
            ['name' => 'Sol Peruano', 'code' => 'PEN'],
            ['name' => 'Real Brasileno', 'code' => 'BRL'],
            ['name' => 'Libra Esterlina', 'code' => 'GBP'],
            ['name' => 'Dolar Canadiense', 'code' => 'CAD'],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'status' => true,
                ]
            );
        }
    }
}
