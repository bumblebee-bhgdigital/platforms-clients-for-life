<?php

/**
 * Clients For Life Service Provider
 * 
 * Dieser Service Provider ist das Herzstück jedes Platform-Moduls.
 * 
 * WICHTIG FÜR LLMs:
 * - Dieser Service Provider folgt dem exakten Muster von HCM und Planner
 * - Alle wichtigen Schritte sind kommentiert
 * - Config wird in register() geladen (Laravel Best Practice)
 * - Modul-Registrierung erfolgt in boot()
 * 
 * ANPASSUNGEN FÜR NEUES MODUL:
 * 1. Ersetze "ClientsForLife" durch deinen Modul-Namen (PascalCase)
 * 2. Ersetze "clients-for-life" durch deinen Modul-Namen (kebab-case)
 * 3. Passe Namespaces an
 * 4. Füge Commands/Tools hinzu falls nötig
 * 
 * @see Platform\Core\PlatformCore für Modul-Registrierung
 * @see Platform\Core\Routing\ModuleRouter für Route-Registrierung
 */

namespace Platform\ClientsForLife;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ClientsForLifeServiceProvider extends ServiceProvider
{
    /**
     * Register Services
     * 
     * Wird VOR boot() aufgerufen.
     * Hier sollten nur leichte Registrierungen erfolgen.
     * 
     * LARAVEL BEST PRACTICE:
     * - Config sollte hier geladen werden (mergeConfigFrom)
     * - Commands können hier registriert werden
     */
    public function register(): void
    {
        /**
         * Config laden
         * 
         * mergeConfigFrom lädt die Config aus dem Package-Verzeichnis
         * und merged sie mit der Config aus config/ (falls vorhanden).
         * 
         * WICHTIG: Muss in register() sein, nicht in boot()!
         */
        $this->mergeConfigFrom(__DIR__.'/../config/clients-for-life.php', 'clients-for-life');
        
        /**
         * Commands registrieren (optional)
         * 
         * Falls dein Modul Artisan Commands hat:
         * 
         * if ($this->app->runningInConsole()) {
         *     $this->commands([
         *         \Platform\ClientsForLife\Console\Commands\YourCommand::class,
         *     ]);
         * }
         */
    }

    /**
     * Boot Services
     * 
     * Wird NACH register() aufgerufen.
     * Hier erfolgt die eigentliche Modul-Registrierung.
     * 
     * REIHENFOLGE IST WICHTIG:
     * 1. Config prüfen (bereits in register() geladen)
     * 2. Modul bei PlatformCore registrieren
     * 3. Routes laden (nur wenn Modul registriert)
     * 4. Migrationen, Views, Livewire registrieren
     */
    public function boot(): void
    {
        /**
         * SCHRITT 1: Modul-Registrierung prüfen
         * 
         * Prüft ob:
         * - Config vorhanden ist
         * - modules-Tabelle existiert (für Datenbank-Registrierung)
         * 
         * Nur wenn beide Bedingungen erfüllt, wird das Modul registriert.
         */
        if (
            config()->has('clients-for-life.routing') &&
            config()->has('clients-for-life.navigation') &&
            PlatformCore::modulesTableExists()
        ) {
            /**
             * Modul bei PlatformCore registrieren
             * 
             * Dies registriert das Modul in:
             * - Der Modul-Registry (für Navigation, Sidebar)
             * - Der Datenbank (modules-Tabelle)
             * 
             * Die Config wird automatisch aus config/clients-for-life.php geladen.
             */
            PlatformCore::registerModule([
                'key'        => 'clients-for-life', // Eindeutiger Schlüssel
                'title'      => 'Clients For Life', // Anzeige-Name
                'group'      => 'dev',
                'routing'    => config('clients-for-life.routing'),
                'guard'      => config('clients-for-life.guard'),
                'navigation' => config('clients-for-life.navigation'),
                'sidebar'    => config('clients-for-life.sidebar'),
            ]);
        }

        /**
         * SCHRITT 2: Routes laden
         * 
         * Routes werden nur geladen, wenn das Modul erfolgreich registriert wurde.
         * 
         * ModuleRouter::group() erstellt automatisch:
         * - Route-Prefix (aus Config)
         * - Middleware (web, auth, etc.)
         * - Domain-Handling (für Subdomain-Modus)
         */
        if (PlatformCore::getModule('clients-for-life')) {
            /**
             * Web-Routes (authentifiziert)
             * 
             * Standard: requireAuth = true
             * Für öffentliche Routes: requireAuth = false
             */
            ModuleRouter::group('clients-for-life', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
            
            /**
             * API-Routes (optional)
             * 
             * Falls dein Modul API-Endpoints hat:
             * 
             * ModuleRouter::apiGroup('clients-for-life', function () {
             *     $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
             * });
             */
        }

        /**
         * SCHRITT 3: Migrationen laden
         * 
         * Lädt alle Migrationen aus database/migrations/
         * Wird automatisch bei `php artisan migrate` ausgeführt.
         */
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        /**
         * SCHRITT 4: Config veröffentlichen
         * 
         * Ermöglicht es, die Config in config/clients-for-life.php zu überschreiben.
         * 
         * Publizieren mit:
         * php artisan vendor:publish --tag=config --provider="Platform\ClientsForLife\ClientsForLifeServiceProvider"
         * 
         * WICHTIG: mergeConfigFrom funktioniert auch OHNE Publizierung!
         */
        $this->publishes([
            __DIR__.'/../config/clients-for-life.php' => config_path('clients-for-life.php'),
        ], 'config');

        /**
         * SCHRITT 5: Views laden
         * 
         * Registriert Views unter dem Namespace 'clients-for-life'
         * 
         * Verwendung in Views:
         * @return view('clients-for-life::livewire.dashboard')
         */
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'clients-for-life');
        
        /**
         * SCHRITT 6: Livewire Components registrieren
         * 
         * Registriert alle Livewire-Komponenten automatisch.
         * 
         * Pattern:
         * - Datei: src/Livewire/Dashboard.php
         * - Alias: clients-for-life.dashboard
         * 
         * Verwendung:
         * <livewire:clients-for-life.dashboard />
         */
        $this->registerLivewireComponents();

        // Morph-Alias, Policies, Organization-Bridge (EntityLinkProvider + Link-Hook), Tools
        $this->registerMorphMap();
        $this->registerPolicies();
        $this->registerEntityLinkProvider();
        $this->registerTools();
        
        /**
         * SCHRITT 7: Tools registrieren (optional)
         * 
         * Falls dein Modul AI/Chat-Tools hat:
         * 
         * $this->registerTools();
         */
    }

    /**
     * Morph-Alias 'clients_for_life' (wie 'project' im Planner) — Voraussetzung für DimensionLinks,
     * HasEntityLinks::getMorphClass() und die Auflösung in der Organization.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            \Platform\ClientsForLife\Models\ClientsForLife::MORPH_ALIAS => \Platform\ClientsForLife\Models\ClientsForLife::class,
        ]);
    }

    protected function registerPolicies(): void
    {
        Gate::policy(
            \Platform\ClientsForLife\Models\ClientsForLife::class,
            \Platform\ClientsForLife\Policies\ClientsForLifePolicy::class
        );
        Gate::policy(
            \Platform\ClientsForLife\Models\ClientsForLifeTemplate::class,
            \Platform\ClientsForLife\Policies\ClientsForLifeTemplatePolicy::class
        );
    }

    /**
     * EntityLinkProvider registrieren (lose Kopplung mit dem Organization-Modul, wie PlannerServiceProvider)
     * und das Link-Event an externe Verknüpfungen koppeln.
     */
    protected function registerEntityLinkProvider(): void
    {
        try {
            resolve(\Platform\Organization\Services\EntityLinkRegistry::class)
                ->register(new \Platform\ClientsForLife\Organization\ClientsForLifeEntityLinkProvider());
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        // Verknüpfungen entstehen nicht nur über den ClientsForLifeService, sondern auch extern
        // (organization.entity.link.POST, Terminal-App "Verknüpfungen"). Der Hook sorgt dafür, dass
        // das Event dispatched und der Bridge-Cache geleert wird — egal, woher die Verknüpfung kommt.
        if (class_exists(\Platform\Organization\Models\OrganizationDimensionLink::class)) {
            \Platform\Organization\Models\OrganizationDimensionLink::created(function ($link) {
                $this->onDimensionLinkChanged($link, \Platform\ClientsForLife\Events\ClientsForLifeLinkChanged::LINKED);
            });
            \Platform\Organization\Models\OrganizationDimensionLink::deleted(function ($link) {
                $this->onDimensionLinkChanged($link, \Platform\ClientsForLife\Events\ClientsForLifeLinkChanged::UNLINKED);
            });
        }
    }

    protected function onDimensionLinkChanged($link, string $action): void
    {
        if ($link->linkable_type !== \Platform\ClientsForLife\Models\ClientsForLife::MORPH_ALIAS) {
            return;
        }

        try {
            $client = \Platform\ClientsForLife\Models\ClientsForLife::withTrashed()->find($link->linkable_id);
            if (! $client) {
                return;
            }
            \Platform\Organization\Services\EntityDimensionBridge::flush();
            \Platform\ClientsForLife\Events\ClientsForLifeLinkChanged::dispatch($client, $action, $link->entity_id ? (int) $link->entity_id : null);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ClientsForLife: DimensionLink-Hook fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Tools für AI/Chat registrieren (Namen nach Plattform-Konvention: <modul>.<ressource-plural>.<VERB>,
     * z. B. planner.projects.GET).
     */
    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
            foreach ([
                \Platform\ClientsForLife\Tools\ListClientsForLifeTool::class,
                \Platform\ClientsForLife\Tools\CreateClientsForLifeTool::class,
                \Platform\ClientsForLife\Tools\UpdateClientsForLifeTool::class,
                \Platform\ClientsForLife\Tools\DeleteClientsForLifeTool::class,
                \Platform\ClientsForLife\Tools\ListClientsForLifeTemplatesTool::class,
                \Platform\ClientsForLife\Tools\CreateClientsForLifeTemplateTool::class,
                \Platform\ClientsForLife\Tools\UpdateClientsForLifeTemplateTool::class,
                \Platform\ClientsForLife\Tools\DeleteClientsForLifeTemplateTool::class,
            ] as $tool) {
                $registry->register(new $tool());
            }
        } catch (\Throwable $e) {
            // ToolRegistry nicht verfügbar
        }
    }

    /**
     * Registriert alle Livewire-Komponenten automatisch
     * 
     * Scant das src/Livewire/ Verzeichnis rekursiv und registriert
     * alle PHP-Dateien als Livewire-Komponenten.
     * 
     * NAMING CONVENTION:
     * - Datei: src/Livewire/Dashboard.php
     * - Namespace: Platform\ClientsForLife\Livewire\Dashboard
     * - Alias: clients-for-life.dashboard
     * 
     * - Datei: src/Livewire/Entity/Index.php
     * - Namespace: Platform\ClientsForLife\Livewire\Entity\Index
     * - Alias: clients-for-life.entity.index
     * 
     * @return void
     */
    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\ClientsForLife\\Livewire';
        $prefix = 'clients-for-life';

        // Prüfe ob Verzeichnis existiert
        if (!is_dir($basePath)) {
            return;
        }

        // Rekursiv alle PHP-Dateien durchsuchen
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            // Nur PHP-Dateien verarbeiten
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Relativen Pfad extrahieren
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            
            // Klassenpfad generieren (z.B. Entity\Index -> Entity\Index)
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            // Prüfe ob Klasse existiert
            if (!class_exists($class)) {
                continue;
            }

            // Alias generieren (z.B. Entity\Index -> entity.index)
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            // Livewire-Komponente registrieren
            Livewire::component($alias, $class);
        }
    }
}
