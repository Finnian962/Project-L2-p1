<?php

declare(strict_types=1);

/**
 * Installeert de stored procedures voor contactpersonen opnieuw, zonder
 * tabellen of seed te wissen.
 *
 * Gebruik: php database/update-contact-procedures.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$verbinding = Database::verbinding();

$verbinding->exec('DROP PROCEDURE IF EXISTS sp_contactpersoon_per_verkoper');
$verbinding->exec('DROP PROCEDURE IF EXISTS sp_contactpersoon_details');
$verbinding->exec('DROP PROCEDURE IF EXISTS sp_contactpersoon_wijzigen');

$verbinding->exec(<<<'SQL'
CREATE PROCEDURE sp_contactpersoon_per_verkoper(IN p_verkoper_id INT UNSIGNED)
BEGIN
    SELECT
        c.id,
        c.naam,
        c.telefoonnummer,
        c.emailadres,
        v.id   AS verkoper_id,
        v.naam AS verkoper_naam
    FROM contactpersoon c
    INNER JOIN contact_per_verkoper cpv
        ON cpv.contactpersoon_id = c.id
       AND cpv.is_actief = 1
    INNER JOIN verkoper v
        ON v.id = cpv.verkoper_id
       AND v.is_actief = 1
    WHERE c.is_actief = 1
      AND v.id = p_verkoper_id
    ORDER BY c.naam ASC;
END
SQL);

$verbinding->exec(<<<'SQL'
CREATE PROCEDURE sp_contactpersoon_details(IN p_contactpersoon_id INT UNSIGNED)
BEGIN
    SELECT
        c.id,
        c.naam,
        c.telefoonnummer,
        c.emailadres,
        v.id   AS verkoper_id,
        v.naam AS verkoper_naam
    FROM contactpersoon c
    INNER JOIN contact_per_verkoper cpv
        ON cpv.contactpersoon_id = c.id
       AND cpv.is_actief = 1
    INNER JOIN verkoper v
        ON v.id = cpv.verkoper_id
       AND v.is_actief = 1
    WHERE c.is_actief = 1
      AND c.id = p_contactpersoon_id
    LIMIT 1;
END
SQL);

$verbinding->exec(<<<'SQL'
CREATE PROCEDURE sp_contactpersoon_wijzigen(
    IN p_contactpersoon_id INT UNSIGNED,
    IN p_naam              VARCHAR(100),
    IN p_telefoonnummer    VARCHAR(25),
    IN p_emailadres        VARCHAR(150)
)
BEGIN
    IF p_naam IS NULL OR TRIM(p_naam) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Naam is verplicht.';
    END IF;

    IF p_telefoonnummer IS NULL OR TRIM(p_telefoonnummer) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Telefoonnummer is verplicht.';
    END IF;

    IF p_emailadres IS NULL OR TRIM(p_emailadres) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'E-mailadres is verplicht.';
    END IF;

    UPDATE contactpersoon
    SET naam           = TRIM(p_naam),
        telefoonnummer = TRIM(p_telefoonnummer),
        emailadres     = TRIM(p_emailadres)
    WHERE id = p_contactpersoon_id
      AND is_actief = 1;

    SELECT
        c.id,
        c.naam,
        c.telefoonnummer,
        c.emailadres,
        v.id   AS verkoper_id,
        v.naam AS verkoper_naam
    FROM contactpersoon c
    INNER JOIN contact_per_verkoper cpv
        ON cpv.contactpersoon_id = c.id
       AND cpv.is_actief = 1
    INNER JOIN verkoper v
        ON v.id = cpv.verkoper_id
       AND v.is_actief = 1
    WHERE c.id = p_contactpersoon_id
      AND c.is_actief = 1
    LIMIT 1;
END
SQL);

echo 'Stored procedures voor contactpersonen zijn bijgewerkt.' . PHP_EOL;
