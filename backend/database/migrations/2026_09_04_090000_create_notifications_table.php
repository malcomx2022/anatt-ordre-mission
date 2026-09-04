<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications in-app (la cloche de l'en-tête).
 *
 * Persistées en plus de l'e-mail : chaque handoff du circuit (OM validé/rejeté,
 * état en attente de signature, état validé/rejeté, lot confirmé, trop-perçu)
 * dépose une ligne pour chaque utilisateur destinataire. `lu_le` NULL = non lue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('titre');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lu_le']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
