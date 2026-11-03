<?php

declare(strict_types=1);

/**
 * Installeert sp_evenement_wijzigen opnieuw, zonder tabellen of seed te wissen.
 *
 * Gebruik: php database/update-event-procedures.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$verbinding = Database::verbinding();
$verbinding->exec('DROP PROCEDURE IF EXISTS sp_evenement_wijzigen');

$verbinding->exec(<<<'SQL'
CREATE PROCEDURE sp_evenement_wijzigen(
    IN p_evenement_id                INT UNSIGNED,
    IN p_naam                        VARCHAR(150),
    IN p_datum                       DATE,
    IN p_locatie                     VARCHAR(150),
    IN p_aantal_tickets_per_tijdslot INT UNSIGNED,
    IN p_beschikbare_stands          INT UNSIGNED,
    IN p_opmerking                   VARCHAR(255)
)
BEGIN
    IF p_naam IS NULL OR TRIM(p_naam) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Naam is verplicht.';
    END IF;

    IF p_datum IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Datum is verplicht.';
    END IF;

    IF p_locatie IS NULL OR TRIM(p_locatie) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Locatie is verplicht.';
    END IF;

    UPDATE evenement
    SET naam                        = TRIM(p_naam),
        datum                       = p_datum,
        locatie                     = TRIM(p_locatie),
        aantal_tickets_per_tijdslot = COALESCE(p_aantal_tickets_per_tijdslot, 0),
        beschikbare_stands          = COALESCE(p_beschikbare_stands, 0),
        opmerking                   = NULLIF(TRIM(p_opmerking), '')
    WHERE id = p_evenement_id
      AND is_actief = 1;

    SELECT id AS evenement_id
    FROM evenement
    WHERE id = p_evenement_id
      AND is_actief = 1;
END
SQL);

echo 'Stored procedure sp_evenement_wijzigen is bijgewerkt.' . PHP_EOL;
