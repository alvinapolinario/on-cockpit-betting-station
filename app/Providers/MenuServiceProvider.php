<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Use a view composer to share menu data with all views
        View::composer('*', function ($view) {
            // Get account type from session
            $accountType = session()->get('account_type');

            // Determine vertical menu file based on account type
            if ($accountType === 'Admin') {
                $verticalMenuFile = base_path('resources/menu/admin_menu.json');
            } else {
                $verticalMenuFile = base_path('resources/menu/default_menu.json');
            }

            // Read and decode the vertical menu JSON
            $verticalMenuData = json_decode(file_get_contents($verticalMenuFile));

            // Read and decode the horizontal menu JSON
            $horizontalMenuFile = base_path('resources/menu/horizontalMenu.json');
            $horizontalMenuData = json_decode(file_get_contents($horizontalMenuFile));

            // Share menu data with the view
            $view->with('menuData', [$verticalMenuData, $horizontalMenuData]);
        });
    }
}
