<?php

/**
 * Clients For Life Web Routes
 * 
 * Diese Datei definiert alle Web-Routes für das Modul.
 * 
 * WICHTIG FÜR LLMs:
 * - Routes werden automatisch mit dem Modul-Prefix versehen (aus Config)
 * - Middleware wird automatisch hinzugefügt (web, auth, etc.)
 * - Route-Namen sollten mit dem Modul-Prefix beginnen
 * 
 * BEISPIEL:
 * Route::get('/', Dashboard::class)->name('clients-for-life.dashboard');
 * 
 * Wird zu: /clients-for-life/ (wenn prefix = 'clients-for-life')
 * 
 * @see Platform\Core\Routing\ModuleRouter für Details
 */

use Platform\ClientsForLife\Livewire\Dashboard;
use Platform\ClientsForLife\Livewire\Showcase;

/**
 * Dashboard Route
 *
 * Hauptübersicht des Moduls
 */
Route::get('/', Dashboard::class)->name('clients-for-life.dashboard');

/**
 * nx-Showcase Route
 *
 * Lebendes Verzeichnis aller x-nx-* Komponenten (Design-Referenz).
 */
Route::get('/showcase', Showcase::class)->name('clients-for-life.showcase');

/**
 * Weitere Routes hinzufügen:
 *
 * Route::get('/entities', Entity\Index::class)->name('clients-for-life.entities.index');
 * Route::get('/entities/{entity}', Entity\Show::class)->name('clients-for-life.entities.show');
 */
