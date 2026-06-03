<?php

return [
    /*
    |--------------------------------------------------------------------------
    | URL de base de l'API distante
    |--------------------------------------------------------------------------
    */
    'url' => env('SATELLITE_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Token Bearer pour authentifier les requêtes
    |--------------------------------------------------------------------------
    */
    'token' => env('SATELLITE_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Timeout HTTP en secondes
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('SATELLITE_API_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Relances HTTP
    |--------------------------------------------------------------------------
    | Nombre de tentatives supplémentaires en cas d'échec de connexion
    | (0 = aucune relance). Le délai initial entre tentatives est en ms.
    */
    'retries' => (int) env('SATELLITE_API_RETRIES', 2),

    'retry_delay' => (int) env('SATELLITE_API_RETRY_DELAY', 200),

    /*
    |--------------------------------------------------------------------------
    | Secret HMAC pour vérifier les webhooks entrants
    |--------------------------------------------------------------------------
    */
    'webhook_secret' => env('SATELLITE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Canal de log Laravel (doit exister dans config/logging.php)
    |--------------------------------------------------------------------------
    */
    'log_channel' => env('SATELLITE_LOG_CHANNEL', 'satellite'),

    /*
    |--------------------------------------------------------------------------
    | Vérification du certificat SSL
    | Mettre à false uniquement pour les environnements avec certs auto-signés
    |--------------------------------------------------------------------------
    */
    'verify_ssl' => (bool) env('SATELLITE_VERIFY_SSL', true),
];
