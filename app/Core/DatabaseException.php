<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Wordt gegooid wanneer een databasebewerking mislukt.
 *
 * De melding van deze exception is altijd gebruikersvriendelijk: de
 * technische details (SQLSTATE, query, driverfout) staan in de technische log.
 */
final class DatabaseException extends RuntimeException
{
    /**
     * Vaste, gebruikersvriendelijke melding bij een database-unhappy flow
     * (verkeerde databasenaam, database plat, query mislukt).
     */
    public const GEBRUIKERSMELDING = 'Onze excuses, er is een error. We zijn ermee bezig.';
}
