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
}
