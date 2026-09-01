<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passe en entiers SIGNÉS les montants de l'état de paiement qui peuvent devenir
 * négatifs : après amendement d'un OM dont l'avance est engagée, la somme des
 * reliquats de la mission peut être un trop-perçu (< 0). Un état des reliquats
 * en BROUILLON, recalculé depuis les OM (récapitulatif / resynchronisation par
 * l'observer), porte alors un `montant_reliquat` — et donc un `montant_total` —
 * négatif. Sur MySQL, une colonne non signée refuserait cette valeur.
 *
 * `montant_avance` reste non signé : la somme des avances est toujours ≥ 0
 * (l'avance engagée ne bouge plus). Cf. docs/amendement-om.md (Phase 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etats_paiement', function (Blueprint $table) {
            $table->bigInteger('montant_total')->default(0)->change();
            $table->bigInteger('montant_reliquat')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('etats_paiement', function (Blueprint $table) {
            $table->unsignedBigInteger('montant_total')->default(0)->change();
            $table->unsignedBigInteger('montant_reliquat')->default(0)->change();
        });
    }
};
