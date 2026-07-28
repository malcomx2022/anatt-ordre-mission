<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SignatureParametres;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signatures électroniques des signataires du circuit, configurées dans les
 * Paramètres : nom, prénoms, fonction et image de signature manuscrite scannée.
 *
 * Ce sont les titulaires EN POSTE. L'état de paiement porte leur identité
 * officielle, indépendamment du compte utilisateur qui a cliqué — lequel reste
 * enregistré pour l'audit.
 *
 * L'image n'est jamais renvoyée par l'API : elle vit sur le disque privé et n'est
 * embarquée qu'au moment de produire le PDF.
 */
class SignatureController extends Controller
{
    /** Identités et présence d'une signature pour les trois postes. */
    public function index(): JsonResponse
    {
        return $this->success(SignatureParametres::tous());
    }

    /** Téléverse l'image de signature d'un poste (PNG ou JPEG, 500 Ko max). */
    public function store(Request $request, string $poste): JsonResponse
    {
        $request->validate([
            // La validation Laravel filtre l'essentiel ; SignatureParametres
            // revérifie le type d'après le CONTENU réel du fichier.
            'signature' => 'required|file|mimes:png,jpg,jpeg|max:500',
        ]);

        SignatureParametres::enregistrerImage($poste, $request->file('signature'));

        return $this->success(
            SignatureParametres::tous(),
            'Signature enregistrée.',
        );
    }

    /** Retire l'image de signature d'un poste. */
    public function destroy(string $poste): JsonResponse
    {
        SignatureParametres::supprimerImage($poste);

        return $this->success(SignatureParametres::tous(), 'Signature supprimée.');
    }
}
