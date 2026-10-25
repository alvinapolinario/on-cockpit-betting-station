<?php

namespace App\Providers;

use App\Models\AppSetting;
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
    try {
      $name = AppSetting::appName();
      config([
        'variables.templateName' => $name,
        'variables.creatorName' => $name,
      ]);
    } catch (\Throwable $e) {
      // Keep config defaults if settings have not been written yet.
    }
  }
}
