<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients_for_life')) {
            return;
        }

        Schema::create('clients_for_life', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // draft | active | archived (Entwurf / aktiv / archiviert)
            $table->string('status', 20)->default('active');

            // Vorlage, aus der die Instanz erzeugt wurde (nur Referenz, keine Laufzeit-Kopplung)
            $table->foreignId('template_id')->nullable()->constrained('clients_for_life_templates')->nullOnDelete();

            // Verantwortung: Owner (User) + verantwortlicher Agent (User vom Typ Agent)
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_agent_user_id')->nullable()->constrained('users')->nullOnDelete();

            // created_by_user_id ist die Ersteller-Spalte, die der Core-Authz-Resolver erkennt
            // (AuthzResolver::ownerColumn): Ersteller sieht seine Instanz, solange sie nicht verortet ist.
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients_for_life');
    }
};
