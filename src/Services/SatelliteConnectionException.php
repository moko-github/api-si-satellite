<?php

declare(strict_types=1);

namespace Moko\Satellite\Services;

use Illuminate\Http\Client\ConnectionException;

/**
 * L'API distante n'a pas répondu du tout : DNS introuvable, connexion refusée, délai dépassé.
 *
 * Distincte d'une erreur de statut — l'API n'a rien renvoyé, il n'y a donc pas de code HTTP à
 * rapporter. `statusCode` vaut `0` pour le dire sans mentir : aucun consommateur ne peut le
 * confondre avec un 503 émis par le serveur.
 *
 * Hérite de {@see SatelliteException} à dessein : tout satellite qui rattrape déjà l'exception
 * générique pour se dégrader quand l'API est indisponible couvre ce cas sans rien changer. Ceux
 * qui veulent distinguer « injoignable » de « a répondu en erreur » rattrapent cette classe-ci.
 */
class SatelliteConnectionException extends SatelliteException
{
    public function __construct(string $endpoint, ConnectionException $previous)
    {
        parent::__construct(
            message: "Satellite client could not reach the API on {$endpoint}: {$previous->getMessage()}",
            statusCode: 0,
            endpoint: $endpoint,
            previous: $previous,
        );
    }
}
