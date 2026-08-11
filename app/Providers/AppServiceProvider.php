<?php

namespace App\Providers;

use App\Models\MetaAdAccount;
use App\Models\MetaPage;
use App\Observers\MetaAdAccountObserver;
use App\Observers\MetaPageObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MetaAdAccount::observe(MetaAdAccountObserver::class);
        MetaPage::observe(MetaPageObserver::class);
    }
}
