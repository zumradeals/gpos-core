<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * G-POS ne possède aucun compte humain canonique (docs/architecture/SATELLITE-CONTRACT.md §3) :
 * ce seeder ne crée donc jamais d'utilisateur. Le seeder de démonstration réel
 * (contexte commercial + adhésion de l'acteur de développement + produit d'exemple) vit dans
 * DevBootstrapSeeder, appelé explicitement en local/testing — jamais ici par défaut.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
