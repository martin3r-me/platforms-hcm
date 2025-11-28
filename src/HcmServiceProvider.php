<?php

namespace Platform\Hcm;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class HcmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Falls in Zukunft Artisan Commands o.ä. nötig sind, hier rein
        
        // Commands registrieren
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Hcm\Console\Commands\ImportJobActivitiesFromMarkdown::class,
                \Platform\Hcm\Console\Commands\SeedHcmLookupData::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Schritt 1: Config laden
        $this->mergeConfigFrom(__DIR__.'/../config/hcm.php', 'hcm');
        
        // Schritt 2: Existenzprüfung (config jetzt verfügbar)
        if (
            config()->has('hcm.routing') &&
            config()->has('hcm.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'hcm',
                'title'      => 'HCM',
                'routing'    => config('hcm.routing'),
                'guard'      => config('hcm.guard'),
                'navigation' => config('hcm.navigation'),
            ]);
        }

        // Schritt 3: Wenn Modul registriert, Routes laden
        if (PlatformCore::getModule('hcm')) {
            ModuleRouter::group('hcm', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
            }, requireAuth: false);

            ModuleRouter::group('hcm', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });

            // API-Routen registrieren
            ModuleRouter::apiGroup('hcm', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }

        // Schritt 4: Migrationen laden
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Schritt 5: Config veröffentlichen
        $this->publishes([
            __DIR__.'/../config/hcm.php' => config_path('hcm.php'),
        ], 'config');

        // Schritt 6: Views & Livewire
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'hcm');
        $this->registerLivewireComponents();
        
        // Schritt 7: Artisan Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Hcm\Console\Commands\ImportBhgData::class,
                \Platform\Hcm\Console\Commands\ImportPayrollTypes::class,
                \Platform\Hcm\Console\Commands\ExportPayrollTypes::class,
                \Platform\Hcm\Console\Commands\SetupTariffStructure::class,
                \Platform\Hcm\Console\Commands\ImportTariffLogic::class,
                \Platform\Hcm\Console\Commands\AssignEmployeeTariffs::class,
                \Platform\Hcm\Console\Commands\ProcessTariffProgressions::class,
                \Platform\Hcm\Console\Commands\SeedHealthInsuranceCompanies::class,
                \Platform\Hcm\Console\Commands\ImportHealthInsuranceCompanies::class,
                \Platform\Hcm\Console\Commands\ImportPayrollTypeMappings::class,
                \Platform\Hcm\Console\Commands\ImportUnifiedHcmData::class,
                \Platform\Hcm\Console\Commands\ImportVwlBenefits::class,
                \Platform\Hcm\Console\Commands\AssignAllianzBkvBenefit::class,
                \Platform\Hcm\Console\Commands\ImportJobRadBenefits::class,
                \Platform\Hcm\Console\Commands\UpdatePayrollTypesSuccessors::class,
                \Platform\Hcm\Console\Commands\DeactivateExpiredContracts::class,
                \Platform\Hcm\Console\Commands\UpdateEmployeeEmailsFromCsv::class,
                \Platform\Hcm\Console\Commands\ImportSollstundenFromCsv::class,
            ]);
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Hcm\\Livewire';
        $prefix = 'hcm';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            // hcm.dashboard aus hcm + dashboard.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            // Debug: Ausgabe der registrierten Komponente
            \Log::info("Registering Livewire component: {$alias} -> {$class}");

            Livewire::component($alias, $class);
        }
    }
}
