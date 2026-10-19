<?php

namespace App\Providers;

use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // The built app migrates automatically but never seeds; roles + admin are required (docs/rbac.md).
        Artisan::call('db:seed', ['--class' => RolesAndAdminSeeder::class, '--force' => true]);

        Window::open()
            ->title('Jejaksa')
            ->width(1440)
            ->height(900)
            ->minWidth(1024)
            ->minHeight(768)
            ->route('dashboard');
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
