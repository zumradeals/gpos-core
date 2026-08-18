<?php

/**
 * Configuration G-POS non couverte par les fichiers config/ standards de Laravel.
 *
 * docs/architecture/SATELLITE-CONTRACT.md §3 : G-POS ne possède aucun compte humain canonique.
 * docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §6.3 : un acteur de développement peut
 * être injecté uniquement en local/testing, jamais silencieusement en production.
 */
return [

    'dev_identity' => [
        // Interrupteur applicatif — n'a d'effet que si l'environnement n'est PAS "production".
        // App\Providers\IdentityServiceProvider refuse de lier DevCoreSessionGateway en production
        // quelle que soit cette valeur, et DevCoreSessionGateway se refuse lui-même à s'exécuter
        // si APP_ENV=production (double verrou, voir tests/Feature/DevIdentityProductionTest.php).
        'enabled' => (bool) env('GPOS_DEV_IDENTITY_ENABLED', true),

        'core_identity_reference' => env('GPOS_DEV_CORE_IDENTITY_REFERENCE'),

        'core_identity_label' => env('GPOS_DEV_CORE_IDENTITY_LABEL', 'Acteur de développement'),
    ],

];
