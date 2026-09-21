<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * Basisklasse voor alle models.
 *
 * Alle databasetoegang loopt via stored procedures. Deze klasse zorgt voor:
 *  - het veilig opbouwen van de CALL met prepared statements (geen SQL-injectie);
 *  - het netjes afsluiten van alle resultsets die MySQL bij een CALL teruggeeft;
 *  - foutafhandeling met try/catch, logging en een begrijpelijke foutmelding.
 */
abstract class Model
{
    protected PDO $verbinding;

    public function __construct()
    {
        $this->verbinding = Database::verbinding();
    }

    /**
     * Roept een stored procedure aan en geeft alle rijen terug.
     *
     * @param  list<mixed> $parameters
     * @return list<array<string, mixed>>
     *
     * @throws DatabaseException Wanneer de query mislukt.
     */
    protected function roepProcedureAan(string $procedure, array $parameters = []): array
    {
        $this->controleerProcedurenaam($procedure);

        $plaatshouders = $parameters === [] ? '' : implode(', ', array_fill(0, count($parameters), '?'));
        $sql = sprintf('CALL %s(%s)', $procedure, $plaatshouders);

        try {
            $statement = $this->verbinding->prepare($sql);
            $statement->execute(array_values($parameters));

            /** @var list<array<string, mixed>> $rijen */
            $rijen = $statement->fetchAll();

            // Een CALL levert naast de SELECT ook een lege statusresultset op.
            // Die moeten we doorlopen, anders blokkeert de volgende query.
            while ($statement->nextRowset()) {
                continue;
            }

            $statement->closeCursor();

            Logger::debug('Stored procedure uitgevoerd', [
                'procedure'  => $procedure,
                'parameters' => $parameters,
                'rijen'      => count($rijen),
            ]);

            return $rijen;
        } catch (PDOException $fout) {
            Logger::error('Stored procedure mislukt', [
                'procedure'  => $procedure,
                'parameters' => $parameters,
                'sqlstate'   => $fout->getCode(),
                'melding'    => $fout->getMessage(),
            ]);

            throw new DatabaseException(
                'De gegevens konden niet worden opgehaald. Probeer het later opnieuw.',
                0,
                $fout
            );
        }
    }

    /**
     * Roept een stored procedure aan en geeft alleen de eerste rij terug.
     *
     * @param  list<mixed> $parameters
     * @return array<string, mixed>|null Null wanneer er geen rij gevonden is.
     *
     * @throws DatabaseException Wanneer de query mislukt.
     */
    protected function roepProcedureAanVoorEenRij(string $procedure, array $parameters = []): ?array
    {
        $rijen = $this->roepProcedureAan($procedure, $parameters);

        return $rijen[0] ?? null;
    }

    /**
     * Beschermt tegen een procedurenaam die uit gebruikersinvoer zou komen.
     *
     * @throws InvalidArgumentException Bij een ongeldige naam.
     */
    private function controleerProcedurenaam(string $procedure): void
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $procedure) !== 1) {
            throw new InvalidArgumentException('Ongeldige naam voor stored procedure: ' . $procedure);
        }
    }
}
