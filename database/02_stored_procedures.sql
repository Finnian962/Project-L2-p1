-- =============================================================================
-- Sneakerness(R) - Stored procedures (Sprint 1: read, Sprint 2: auth en stands)
-- -----------------------------------------------------------------------------
-- Bijna alle acties van de webapplicatie lopen via deze stored procedures.
-- De applicatie voert dus geen losse SELECT-, INSERT- of UPDATE-statements
-- uit; dat houdt de SQL op één plek en maakt de queries herbruikbaar.
--
-- Conventie: sp_<entiteit>_<actie>
-- =============================================================================

USE sneakerness;

DROP PROCEDURE IF EXISTS sp_evenement_overzicht;
DROP PROCEDURE IF EXISTS sp_evenement_details;
DROP PROCEDURE IF EXISTS sp_evenement_toevoegen;
DROP PROCEDURE IF EXISTS sp_tijdslot_overzicht;
DROP PROCEDURE IF EXISTS sp_standtype_overzicht;
DROP PROCEDURE IF EXISTS sp_verkoper_overzicht;
DROP PROCEDURE IF EXISTS sp_partner_overzicht;
DROP PROCEDURE IF EXISTS sp_sidestand_overzicht;
DROP PROCEDURE IF EXISTS sp_contactpersoon_per_verkoper;
DROP PROCEDURE IF EXISTS sp_ticket_toevoegen;
DROP PROCEDURE IF EXISTS sp_tijdslot_toevoegen;
DROP PROCEDURE IF EXISTS sp_bestelling_plaatsen;
DROP PROCEDURE IF EXISTS sp_bestelling_bevesting;
DROP PROCEDURE IF EXISTS sp_rapport_ticketverkoop;
DROP PROCEDURE IF EXISTS sp_rapport_standverhuur;
DROP PROCEDURE IF EXISTS sp_gebruiker_inloggen;
DROP PROCEDURE IF EXISTS sp_stand_overzicht;
DROP PROCEDURE IF EXISTS sp_stand_beschikbaar;
DROP PROCEDURE IF EXISTS sp_stand_toevoegen;
DROP PROCEDURE IF EXISTS sp_stand_reserveren;
DROP PROCEDURE IF EXISTS sp_reservering_overzicht;
DROP PROCEDURE IF EXISTS sp_demo_zichtbaarheid_events;
DROP PROCEDURE IF EXISTS sp_demo_zichtbaarheid_tijdsloten;

DELIMITER $$

-- -----------------------------------------------------------------------------
-- sp_evenement_overzicht
-- Alle actieve evenementen, gesorteerd op datum (vroeg -> laat).
-- Levert per evenement ook de afgeleide ticketinformatie:
--   - aantal tijdsloten en dagen (JOIN op prijs)
--   - verkochte en nog beschikbare tickets (LEFT JOIN op ticket)
-- Parameter p_zoekterm is optioneel: leeg of NULL geeft alle evenementen.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_evenement_overzicht(IN p_zoekterm VARCHAR(100))
BEGIN
    SELECT
        e.id,
        e.naam,
        e.datum,
        e.locatie,
        e.aantal_tickets_per_tijdslot,
        e.beschikbare_stands,
        e.opmerking,
        MIN(p.datum)                                   AS eerste_dag,
        MAX(p.datum)                                   AS laatste_dag,
        COUNT(DISTINCT p.datum)                        AS aantal_dagen,
        COUNT(DISTINCT p.id)                           AS aantal_tijdsloten,
        MIN(p.tarief)                                  AS laagste_tarief,
        MAX(p.tarief)                                  AS hoogste_tarief,
        COUNT(DISTINCT p.id) * e.aantal_tickets_per_tijdslot AS totale_capaciteit,
        COALESCE(SUM(t.aantal_tickets), 0)             AS verkochte_tickets,
        (COUNT(DISTINCT p.id) * CAST(e.aantal_tickets_per_tijdslot AS SIGNED))
            - COALESCE(SUM(t.aantal_tickets), 0)       AS beschikbare_tickets,
        (
            SELECT COUNT(*)
            FROM stand s
            WHERE s.evenement_id = e.id
              AND s.is_actief = 1
              AND s.verhuurd_status = 1
        )                                              AS verhuurde_stands
    FROM evenement e
    LEFT JOIN prijs p
        ON p.evenement_id = e.id
       AND p.is_actief = 1
    LEFT JOIN ticket t
        ON t.prijs_id = p.id
       AND t.is_actief = 1
    WHERE e.is_actief = 1
      AND (
            p_zoekterm IS NULL
         OR p_zoekterm = ''
         OR e.naam LIKE CONCAT('%', p_zoekterm, '%')
         OR e.locatie LIKE CONCAT('%', p_zoekterm, '%')
      )
    GROUP BY
        e.id, e.naam, e.datum, e.locatie,
        e.aantal_tickets_per_tijdslot, e.beschikbare_stands, e.opmerking
    ORDER BY e.datum ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_evenement_details
-- Eén actief evenement met dezelfde afgeleide gegevens als het overzicht.
-- Geeft nul rijen terug wanneer het evenement niet bestaat of inactief is;
-- de controller vertaalt dat naar een nette melding (unhappy flow).
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_evenement_details(IN p_evenement_id INT UNSIGNED)
BEGIN
    SELECT
        e.id,
        e.naam,
        e.datum,
        e.locatie,
        e.aantal_tickets_per_tijdslot,
        e.beschikbare_stands,
        e.opmerking,
        MIN(p.datum)                                   AS eerste_dag,
        MAX(p.datum)                                   AS laatste_dag,
        COUNT(DISTINCT p.datum)                        AS aantal_dagen,
        COUNT(DISTINCT p.id)                           AS aantal_tijdsloten,
        MIN(p.tarief)                                  AS laagste_tarief,
        MAX(p.tarief)                                  AS hoogste_tarief,
        COUNT(DISTINCT p.id) * e.aantal_tickets_per_tijdslot AS totale_capaciteit,
        COALESCE(SUM(t.aantal_tickets), 0)             AS verkochte_tickets,
        (COUNT(DISTINCT p.id) * CAST(e.aantal_tickets_per_tijdslot AS SIGNED))
            - COALESCE(SUM(t.aantal_tickets), 0)       AS beschikbare_tickets,
        (
            SELECT COUNT(*)
            FROM stand s
            WHERE s.evenement_id = e.id
              AND s.is_actief = 1
              AND s.verhuurd_status = 0
        )                                              AS vrije_stands
    FROM evenement e
    LEFT JOIN prijs p
        ON p.evenement_id = e.id
       AND p.is_actief = 1
    LEFT JOIN ticket t
        ON t.prijs_id = p.id
       AND t.is_actief = 1
    WHERE e.is_actief = 1
      AND e.id = p_evenement_id
    GROUP BY
        e.id, e.naam, e.datum, e.locatie,
        e.aantal_tickets_per_tijdslot, e.beschikbare_stands, e.opmerking;
END$$

-- -----------------------------------------------------------------------------
-- sp_evenement_toevoegen
-- Slaat een nieuw event op. Verplichte velden: naam, datum, locatie.
-- Tickets per tijdslot en stands mogen 0 zijn; de PHP-laag controleert
-- of het getallen zijn. Geeft het nieuwe id terug.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_evenement_toevoegen(
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

    INSERT INTO evenement (
        naam,
        datum,
        locatie,
        aantal_tickets_per_tijdslot,
        beschikbare_stands,
        opmerking
    ) VALUES (
        TRIM(p_naam),
        p_datum,
        TRIM(p_locatie),
        COALESCE(p_aantal_tickets_per_tijdslot, 0),
        COALESCE(p_beschikbare_stands, 0),
        NULLIF(TRIM(p_opmerking), '')
    );

    SELECT LAST_INSERT_ID() AS evenement_id;
END$$

-- -----------------------------------------------------------------------------
-- sp_tijdslot_overzicht
-- Alle tijdsloten met tarief en actuele beschikbaarheid.
-- INNER JOIN evenement  -> alleen tijdsloten van actieve evenementen
-- LEFT  JOIN ticket     -> reeds verkochte tickets per tijdslot
-- p_evenement_id = 0 of NULL geeft de tijdsloten van alle evenementen.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_tijdslot_overzicht(IN p_evenement_id INT UNSIGNED)
BEGIN
    SELECT
        p.id                                AS prijs_id,
        p.evenement_id,
        e.naam                              AS evenement_naam,
        e.locatie                           AS evenement_locatie,
        p.datum,
        p.tijdslot,
        p.tarief,
        p.opmerking,
        e.aantal_tickets_per_tijdslot       AS capaciteit,
        COALESCE(SUM(t.aantal_tickets), 0)  AS verkochte_tickets,
        GREATEST(
            CAST(e.aantal_tickets_per_tijdslot AS SIGNED)
                - COALESCE(SUM(t.aantal_tickets), 0),
            0
        )                                   AS beschikbare_tickets
    FROM prijs p
    INNER JOIN evenement e
        ON e.id = p.evenement_id
       AND e.is_actief = 1
    LEFT JOIN ticket t
        ON t.prijs_id = p.id
       AND t.is_actief = 1
    WHERE p.is_actief = 1
      AND (p_evenement_id IS NULL OR p_evenement_id = 0 OR p.evenement_id = p_evenement_id)
    GROUP BY
        p.id, p.evenement_id, e.naam, e.locatie, p.datum,
        p.tijdslot, p.tarief, p.opmerking, e.aantal_tickets_per_tijdslot
    ORDER BY p.datum ASC, p.tijdslot ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_standtype_overzicht
-- Standtypes (AA+, AA, A) per evenement met prijsrange en beschikbaarheid.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_standtype_overzicht(IN p_evenement_id INT UNSIGNED)
BEGIN
    SELECT
        s.evenement_id,
        e.naam                                                    AS evenement_naam,
        s.stand_type,
        COUNT(*)                                                  AS aantal_stands,
        SUM(CASE WHEN s.verhuurd_status = 1 THEN 1 ELSE 0 END)    AS verhuurde_stands,
        SUM(CASE WHEN s.verhuurd_status = 0 THEN 1 ELSE 0 END)    AS vrije_stands,
        MIN(s.prijs)                                              AS laagste_prijs,
        MAX(s.prijs)                                              AS hoogste_prijs
    FROM stand s
    INNER JOIN evenement e
        ON e.id = s.evenement_id
       AND e.is_actief = 1
    WHERE s.is_actief = 1
      AND (p_evenement_id IS NULL OR p_evenement_id = 0 OR s.evenement_id = p_evenement_id)
    GROUP BY s.evenement_id, e.naam, s.stand_type
    ORDER BY e.naam ASC, FIELD(s.stand_type, 'AA+', 'AA', 'A');
END$$

-- -----------------------------------------------------------------------------
-- sp_verkoper_overzicht
-- Verkopers met een stand op een actief evenement.
-- p_soort filtert op wat de verkoper verkoopt (leeg = alles).
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_verkoper_overzicht(
    IN p_evenement_id INT UNSIGNED,
    IN p_soort VARCHAR(60)
)
BEGIN
    SELECT
        v.id,
        v.naam,
        v.speciale_status,
        v.verkoopt_soort,
        v.stand_type,
        v.dagen,
        v.logo,
        v.beschrijving,
        GROUP_CONCAT(DISTINCT e.naam ORDER BY e.naam SEPARATOR ', ') AS evenement_naam,
        COUNT(DISTINCT s.id)                    AS aantal_stands,
        COUNT(DISTINCT cpv.contactpersoon_id)   AS aantal_contactpersonen
    FROM verkoper v
    LEFT JOIN stand s
        ON s.verkoper_id = v.id
       AND s.is_actief = 1
    LEFT JOIN evenement e
        ON e.id = s.evenement_id
       AND e.is_actief = 1
    LEFT JOIN contact_per_verkoper cpv
        ON cpv.verkoper_id = v.id
       AND cpv.is_actief = 1
    WHERE v.is_actief = 1
      AND (p_evenement_id IS NULL OR p_evenement_id = 0 OR s.evenement_id = p_evenement_id)
      AND (p_soort IS NULL OR p_soort = '' OR v.verkoopt_soort = p_soort)
    GROUP BY
        v.id, v.naam, v.speciale_status, v.verkoopt_soort,
        v.stand_type, v.dagen, v.logo, v.beschrijving
    ORDER BY v.speciale_status DESC, v.naam ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_partner_overzicht
-- Partners (verkopers met speciale status) inclusief logo en extra informatie.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_partner_overzicht(IN p_evenement_id INT UNSIGNED)
BEGIN
    SELECT
        v.id,
        v.naam,
        v.verkoopt_soort,
        v.stand_type,
        v.dagen,
        v.logo,
        v.beschrijving,
        GROUP_CONCAT(DISTINCT e.naam ORDER BY e.naam SEPARATOR ', ') AS evenement_naam,
        COUNT(DISTINCT s.id)                                          AS aantal_stands
    FROM verkoper v
    INNER JOIN stand s
        ON s.verkoper_id = v.id
       AND s.is_actief = 1
    INNER JOIN evenement e
        ON e.id = s.evenement_id
       AND e.is_actief = 1
    WHERE v.is_actief = 1
      AND v.speciale_status = 1
      AND (p_evenement_id IS NULL OR p_evenement_id = 0 OR s.evenement_id = p_evenement_id)
    GROUP BY v.id, v.naam, v.verkoopt_soort, v.stand_type, v.dagen, v.logo, v.beschrijving
    ORDER BY v.naam ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_sidestand_overzicht
-- Alle side-stands: eten en drinken, kids corner, customizers, tattoo,
-- barbershop en DJ-sets (alles behalve sneakerverkoop).
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_sidestand_overzicht(IN p_evenement_id INT UNSIGNED)
BEGIN
    SELECT
        v.id,
        v.naam,
        v.verkoopt_soort,
        v.speciale_status,
        v.logo,
        v.beschrijving,
        v.dagen,
        GROUP_CONCAT(DISTINCT e.naam ORDER BY e.naam SEPARATOR ', ') AS evenement_naam,
        COUNT(DISTINCT s.id)    AS aantal_stands
    FROM verkoper v
    INNER JOIN stand s
        ON s.verkoper_id = v.id
       AND s.is_actief = 1
    INNER JOIN evenement e
        ON e.id = s.evenement_id
       AND e.is_actief = 1
    WHERE v.is_actief = 1
      AND v.verkoopt_soort <> 'Sneakers'
      AND (p_evenement_id IS NULL OR p_evenement_id = 0 OR s.evenement_id = p_evenement_id)
    GROUP BY v.id, v.naam, v.verkoopt_soort, v.speciale_status,
             v.logo, v.beschrijving, v.dagen
    ORDER BY v.verkoopt_soort ASC, v.naam ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_contactpersoon_per_verkoper
-- Contactpersonen van één verkoper via de koppeltabel contact_per_verkoper.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_contactpersoon_per_verkoper(IN p_verkoper_id INT UNSIGNED)
BEGIN
    SELECT
        c.id,
        c.naam,
        c.telefoonnummer,
        c.emailadres,
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
END$$

-- -----------------------------------------------------------------------------
-- sp_rapport_ticketverkoop
-- Realtime rapport voor de organisator: verkochte tickets en omzet per event.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_rapport_ticketverkoop()
BEGIN
    SELECT
        e.id,
        e.naam,
        e.datum,
        e.locatie,
        COUNT(DISTINCT t.id)                            AS aantal_boekingen,
        COUNT(DISTINCT b.id)                            AS aantal_bezoekers,
        COALESCE(SUM(t.aantal_tickets), 0)              AS verkochte_tickets,
        COALESCE(SUM(t.aantal_tickets * p.tarief), 0)   AS omzet
    FROM evenement e
    LEFT JOIN ticket t
        ON t.evenement_id = e.id
       AND t.is_actief = 1
    LEFT JOIN prijs p
        ON p.id = t.prijs_id
    LEFT JOIN bezoeker b
        ON b.id = t.bezoeker_id
       AND b.is_actief = 1
    WHERE e.is_actief = 1
    GROUP BY e.id, e.naam, e.datum, e.locatie
    ORDER BY e.datum ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_rapport_standverhuur
-- Realtime rapport voor de organisator: verhuurde stands en omzet per event.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_rapport_standverhuur()
BEGIN
    SELECT
        e.id,
        e.naam,
        e.datum,
        e.locatie,
        COUNT(s.id)                                                     AS aantal_stands,
        SUM(CASE WHEN s.verhuurd_status = 1 THEN 1 ELSE 0 END)          AS verhuurde_stands,
        SUM(CASE WHEN s.verhuurd_status = 0 THEN 1 ELSE 0 END)          AS vrije_stands,
        COUNT(DISTINCT v.id)                                            AS aantal_verkopers,
        COALESCE(SUM(CASE WHEN s.verhuurd_status = 1 THEN s.prijs END), 0) AS omzet
    FROM evenement e
    LEFT JOIN stand s
        ON s.evenement_id = e.id
       AND s.is_actief = 1
    LEFT JOIN verkoper v
        ON v.id = s.verkoper_id
       AND v.is_actief = 1
    WHERE e.is_actief = 1
    GROUP BY e.id, e.naam, e.datum, e.locatie
    ORDER BY e.datum ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_tijdslot_toevoegen
-- Maakt een nieuw tijdslot (tabel prijs) aan: de tweede schrijfactie (create)
-- van het project.
--
-- Een medewerker vult editie, dag, starttijd en tarief in. Het nieuwe
-- tijdslot verschijnt daarna direct op de ticketpagina (/tickets) met de
-- beschikbaarheid van de editie (evenement.aantal_tickets_per_tijdslot), zodat
-- er meteen tickets voor verkocht kunnen worden.
--
-- Controleert of de editie actief is, of de dag niet vóór de editie valt en of
-- er op dit tijdstip nog geen tijdslot staat (UNIQUE KEY uq_prijs_slot).
-- Geeft het nieuwe tijdslotnummer (prijs.id) terug.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_tijdslot_toevoegen(
    IN p_evenement_id INT UNSIGNED,
    IN p_datum        DATE,
    IN p_tijdslot     TIME,
    IN p_tarief       DECIMAL(6, 2),
    IN p_opmerking    VARCHAR(255)
)
BEGIN
    DECLARE v_startdatum DATE DEFAULT NULL;
    DECLARE v_bestaand   INT  DEFAULT 0;

    IF p_datum IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Vul een datum in.';
    END IF;

    IF p_tijdslot IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Vul een starttijd in.';
    END IF;

    IF p_tarief IS NULL OR p_tarief <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Vul een geldig tarief in.';
    END IF;

    SELECT e.datum
      INTO v_startdatum
    FROM evenement e
    WHERE e.id = p_evenement_id
      AND e.is_actief = 1;

    IF v_startdatum IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'De gekozen editie is niet gevonden of niet actief.';
    END IF;

    IF p_datum < v_startdatum THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'De datum mag niet voor de start van de editie liggen.';
    END IF;

    SELECT COUNT(*)
      INTO v_bestaand
    FROM prijs
    WHERE evenement_id = p_evenement_id
      AND datum = p_datum
      AND tijdslot = p_tijdslot;

    IF v_bestaand > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Voor dit tijdstip bestaat al een tijdslot.';
    END IF;

    INSERT INTO prijs (evenement_id, datum, tijdslot, tarief, is_actief, opmerking)
    VALUES (p_evenement_id, p_datum, p_tijdslot, p_tarief, 1, p_opmerking);

    SELECT LAST_INSERT_ID() AS tijdslotnummer;
END$$

-- -----------------------------------------------------------------------------
-- sp_ticket_toevoegen
-- Voegt een ticket toe (tabel ticket) voor een tijdslot van een evenement:
-- de eerste schrijfactie (create) van het project.
--
-- Een medewerker vult editie, tijdslot en aantal in; er hoeft geen bezoeker
-- te zijn, dus bezoeker_id blijft NULL. De procedure controleert zelf of de
-- editie actief is, of het tijdslot bij die editie hoort en of er nog genoeg
-- tickets beschikbaar zijn, zodat er nooit meer verkocht wordt dan de
-- capaciteit. Geeft het nieuwe ticketnummer terug.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_ticket_toevoegen(
    IN p_evenement_id INT UNSIGNED,
    IN p_prijs_id     INT UNSIGNED,
    IN p_aantal       INT UNSIGNED
)
BEGIN
    DECLARE v_event_actief TINYINT DEFAULT NULL;
    DECLARE v_prijs_actief TINYINT DEFAULT NULL;
    DECLARE v_datum        DATE    DEFAULT NULL;
    DECLARE v_capaciteit   INT     DEFAULT 0;
    DECLARE v_verkocht     INT     DEFAULT 0;

    IF p_aantal IS NULL OR p_aantal < 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Vul een geldig aantal in.';
    END IF;

    SELECT e.is_actief, e.aantal_tickets_per_tijdslot
      INTO v_event_actief, v_capaciteit
    FROM evenement e
    WHERE e.id = p_evenement_id;

    IF v_event_actief IS NULL OR v_event_actief = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'De gekozen editie is niet gevonden of niet actief.';
    END IF;

    SELECT p.is_actief, p.datum
      INTO v_prijs_actief, v_datum
    FROM prijs p
    WHERE p.id = p_prijs_id
      AND p.evenement_id = p_evenement_id;

    IF v_prijs_actief IS NULL OR v_prijs_actief = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Het gekozen tijdslot hoort niet bij deze editie of is niet actief.';
    END IF;

    SELECT COALESCE(SUM(t.aantal_tickets), 0)
      INTO v_verkocht
    FROM ticket t
    WHERE t.prijs_id = p_prijs_id
      AND t.is_actief = 1;

    IF p_aantal > (v_capaciteit - v_verkocht) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Er zijn niet genoeg tickets beschikbaar voor dit tijdslot.';
    END IF;

    INSERT INTO ticket (bezoeker_id, evenement_id, prijs_id, aantal_tickets, datum, opmerking)
    VALUES (NULL, p_evenement_id, p_prijs_id, p_aantal, v_datum, 'Toegevoegd door medewerker');

    SELECT LAST_INSERT_ID() AS ticketnummer;
END$$

-- -----------------------------------------------------------------------------
-- sp_bestelling_plaatsen
-- Plaats een bestelling van een bezoeker (user story "Als bezoeker wil ik een
-- ticket voor een evenement kunnen kopen").
--
-- De bezoeker vult naam en e-mailadres in; bestaat dat e-mailadres al, dan
-- wordt de bestaande bezoeker hergebruikt en komt er geen tweede rij. Daarna
-- wordt het ticket gekoppeld, zodat er wél een bezoeker aan vastzit.
--
-- Alles gebeurt in één transactie met de rij van het tijdslot vastgepakt
-- (FOR UPDATE), zodat er nooit meer verkocht wordt dan de capaciteit en een
-- mislukte bestelling niets achterlaat. Geeft ticketnummer en bezoeker-id
-- terug.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_bestelling_plaatsen(
    IN p_evenement_id INT UNSIGNED,
    IN p_prijs_id     INT UNSIGNED,
    IN p_aantal       INT UNSIGNED,
    IN p_naam         VARCHAR(100),
    IN p_emailadres   VARCHAR(150)
)
BEGIN
    DECLARE v_event_actief TINYINT      DEFAULT NULL;
    DECLARE v_prijs_actief TINYINT      DEFAULT NULL;
    DECLARE v_datum        DATE         DEFAULT NULL;
    DECLARE v_capaciteit   INT          DEFAULT 0;
    DECLARE v_verkocht     INT          DEFAULT 0;
    DECLARE v_bezoeker     INT UNSIGNED DEFAULT NULL;

    IF p_naam IS NULL OR TRIM(p_naam) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Vul je naam in.';
    END IF;

    IF CHAR_LENGTH(p_naam) > 100 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Je naam mag maximaal 100 tekens bevatten.';
    END IF;

    IF p_emailadres IS NULL OR TRIM(p_emailadres) = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Vul je e-mailadres in.';
    END IF;

    IF CHAR_LENGTH(p_emailadres) > 150 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Het e-mailadres mag maximaal 150 tekens bevatten.';
    END IF;

    IF p_aantal IS NULL OR p_aantal < 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Vul een geldig aantal in.';
    END IF;

    START TRANSACTION;

    SELECT e.is_actief, e.aantal_tickets_per_tijdslot
      INTO v_event_actief, v_capaciteit
    FROM evenement e
    WHERE e.id = p_evenement_id
    FOR UPDATE;

    IF v_event_actief IS NULL OR v_event_actief = 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'De gekozen editie is niet gevonden of niet actief.';
    END IF;

    SELECT p.is_actief, p.datum
      INTO v_prijs_actief, v_datum
    FROM prijs p
    WHERE p.id = p_prijs_id
      AND p.evenement_id = p_evenement_id
    FOR UPDATE;

    IF v_prijs_actief IS NULL OR v_prijs_actief = 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Het gekozen tijdslot hoort niet bij deze editie of is niet actief.';
    END IF;

    SELECT COALESCE(SUM(t.aantal_tickets), 0)
      INTO v_verkocht
    FROM ticket t
    WHERE t.prijs_id = p_prijs_id
      AND t.is_actief = 1;

    IF p_aantal > (v_capaciteit - v_verkocht) THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Er zijn niet genoeg tickets beschikbaar voor dit tijdslot.';
    END IF;

    SELECT id
      INTO v_bezoeker
    FROM bezoeker
    WHERE emailadres = TRIM(p_emailadres)
      AND is_actief = 1
    LIMIT 1;

    IF v_bezoeker IS NULL THEN
        INSERT INTO bezoeker (naam, emailadres)
        VALUES (TRIM(p_naam), TRIM(p_emailadres));

        SET v_bezoeker = LAST_INSERT_ID();
    END IF;

    INSERT INTO ticket (bezoeker_id, evenement_id, prijs_id, aantal_tickets, datum, opmerking)
    VALUES (v_bezoeker, p_evenement_id, p_prijs_id, p_aantal, v_datum, CONCAT('Bestelling van ', TRIM(p_naam)));

    COMMIT;

    SELECT LAST_INSERT_ID() AS ticketnummer, v_bezoeker AS bezoeker_id;
END$$

-- -----------------------------------------------------------------------------
-- sp_bestelling_bevesting
-- Gegevens van een geplaatste bestelling voor de bevestiging die de bezoeker
-- op het scherm ziet (de e-mail zelf wordt vastgelegd in de technische log).
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_bestelling_bevesting(IN p_ticket_id INT UNSIGNED)
BEGIN
    SELECT
        t.id                     AS ticketnummer,
        b.naam                   AS bezoeker_naam,
        b.emailadres,
        e.naam                   AS evenement_naam,
        p.datum,
        p.tijdslot,
        p.tarief,
        t.aantal_tickets         AS aantal,
        (p.tarief * t.aantal_tickets) AS totaal
    FROM ticket t
    INNER JOIN prijs p
        ON p.id = t.prijs_id
    INNER JOIN evenement e
        ON e.id = t.evenement_id
    INNER JOIN bezoeker b
        ON b.id = t.bezoeker_id
    WHERE t.id = p_ticket_id;
END$$

-- -----------------------------------------------------------------------------
-- sp_demo_zichtbaarheid_events
-- Hulpprocedure voor de sprintreview: zet alle evenementen tijdelijk op
-- inactief (0) om de unhappy flow van het eventoverzicht te demonstreren,
-- of weer op actief (1) voor de happy flow.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_demo_zichtbaarheid_events(IN p_is_actief TINYINT)
BEGIN
    UPDATE evenement
    SET is_actief = IF(p_is_actief = 1, 1, 0);

    SELECT ROW_COUNT() AS aantal_gewijzigd;
END$$

-- -----------------------------------------------------------------------------
-- sp_demo_zichtbaarheid_tijdsloten
-- Hulpprocedure voor de sprintreview: zet de tijdsloten (tabel prijs) van
-- een evenement tijdelijk op inactief om de unhappy flow van de ticketpagina
-- te demonstreren ("Er zijn momenteel geen tijdsloten beschikbaar."),
-- of weer op actief voor de happy flow.
-- Parameter p_evenement_id is optioneel: 0 of NULL verbergt de tijdsloten
-- van álle evenementen.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_demo_zichtbaarheid_tijdsloten(
    IN p_evenement_id INT UNSIGNED,
    IN p_is_actief    TINYINT
)
BEGIN
    IF p_evenement_id IS NULL OR p_evenement_id = 0 THEN
        UPDATE prijs
        SET is_actief = IF(p_is_actief = 1, 1, 0);
    ELSE
        UPDATE prijs
        SET is_actief = IF(p_is_actief = 1, 1, 0)
        WHERE evenement_id = p_evenement_id;
    END IF;

    SELECT ROW_COUNT() AS aantal_gewijzigd;
END$$

-- -----------------------------------------------------------------------------
-- sp_gebruiker_inloggen
-- Zoekt een actief inlogaccount op gebruikersnaam.
-- Het wachtwoord komt als hash mee terug; de applicatie vergelijkt dat met
-- password_verify(). Bestaat het account niet, dan levert de procedure nul
-- rijen op en toont de controller dezelfde melding als bij een verkeerd
-- wachtwoord (geen informatie lekken over bestaande gebruikersnamen).
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_gebruiker_inloggen(IN p_gebruikersnaam VARCHAR(50))
BEGIN
    SELECT
        g.id,
        g.naam,
        g.gebruikersnaam,
        g.wachtwoord,
        g.rol,
        g.verkoper_id
    FROM gebruiker g
    WHERE g.gebruikersnaam = p_gebruikersnaam
      AND g.is_actief = 1
    LIMIT 1;
END$$

-- -----------------------------------------------------------------------------
-- sp_stand_overzicht
-- Alle stands van een evenement met verhuurder en status, voor het
-- beheeroverzicht. p_evenement_id = 0 of NULL geeft alle stands.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_stand_overzicht(IN p_evenement_id INT UNSIGNED)
BEGIN
    SELECT
        s.id,
        s.evenement_id,
        e.naam                     AS evenement_naam,
        s.stand_type,
        s.prijs,
        s.verhuurd_status,
        CASE WHEN s.verhuurd_status = 1 THEN 'Gereserveerd' ELSE 'Beschikbaar' END AS status,
        s.opmerking,
        s.datum_aangemaakt,
        v.id                       AS verkoper_id,
        v.naam                     AS verkoper_naam
    FROM stand s
    INNER JOIN evenement e
        ON e.id = s.evenement_id
       AND e.is_actief = 1
    LEFT JOIN verkoper v
        ON v.id = s.verkoper_id
       AND v.is_actief = 1
    WHERE s.is_actief = 1
      AND (p_evenement_id IS NULL OR p_evenement_id = 0 OR s.evenement_id = p_evenement_id)
    ORDER BY e.datum ASC, s.id ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_stand_beschikbaar
-- De stands die een standhouder nog kan reserveren: vrij, actief en van een
-- actief evenement. Dit is de bron voor de pagina met beschikbare standen.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_stand_beschikbaar(IN p_evenement_id INT UNSIGNED)
BEGIN
    SELECT
        s.id,
        s.evenement_id,
        e.naam                     AS evenement_naam,
        e.datum                    AS evenement_datum,
        e.locatie                  AS evenement_locatie,
        s.stand_type,
        s.prijs,
        s.verhuurd_status,
        'Beschikbaar'              AS status
    FROM stand s
    INNER JOIN evenement e
        ON e.id = s.evenement_id
       AND e.is_actief = 1
    WHERE s.is_actief = 1
      AND s.verhuurd_status = 0
      AND (p_evenement_id IS NULL OR p_evenement_id = 0 OR s.evenement_id = p_evenement_id)
    ORDER BY e.datum ASC, FIELD(s.stand_type, 'AA+', 'AA', 'A'), s.id ASC;
END$$

-- -----------------------------------------------------------------------------
-- sp_stand_toevoegen
-- Voegt een nieuwe stand toe aan een evenement (actie van de beheerder).
-- De stand start vrij (verhuurd_status = 0) en is daarmee meteen zichtbaar
-- in het standenaanbod op /stands.
-- Loopt een FOREIGN KEY- of ENUM-fout aan, dan gooit MySQL een fout die de
-- Model-basislaat vangt en omzet in een nette melding.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_stand_toevoegen(
    IN p_evenement_id INT UNSIGNED,
    IN p_stand_type   VARCHAR(3),
    IN p_prijs        DECIMAL(8, 2),
    IN p_opmerking    VARCHAR(255)
)
BEGIN
    INSERT INTO stand (evenement_id, stand_type, prijs, verhuurd_status, opmerking)
    VALUES (p_evenement_id, p_stand_type, p_prijs, 0, p_opmerking);

    SELECT LAST_INSERT_ID() AS stand_id;
END$$

-- -----------------------------------------------------------------------------
-- sp_stand_reserveren
-- Reserveert een stand voor een standhouder.
-- De WHERE-clausule controleert dat de stand nog vrij is: twee standhouders
-- kunnen daardoor nooit allebei dezelfde stand reserveren.
-- p_verkoper_id = 0 betekent dat de standhouder geen verkoper gekoppeld heeft;
-- dan blijft de stand vrij van een verhuurder maar is hij wel gereserveerd.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_stand_reserveren(
    IN p_stand_id    INT UNSIGNED,
    IN p_verkoper_id INT UNSIGNED
)
BEGIN
    DECLARE v_aantal INT UNSIGNED DEFAULT 0;

    UPDATE stand
    SET verhuurd_status = 1,
        verkoper_id     = CASE WHEN p_verkoper_id = 0 THEN verkoper_id ELSE p_verkoper_id END
    WHERE id = p_stand_id
      AND is_actief = 1
      AND verhuurd_status = 0;

    SET v_aantal = ROW_COUNT();

    SELECT
        v_aantal                                             AS aantal_gewijzigd,
        CASE WHEN v_aantal = 1 THEN 'Gereserveerd'
             ELSE (SELECT CASE WHEN verhuurd_status = 1 THEN 'Gereserveerd' ELSE 'Beschikbaar' END
                   FROM stand WHERE id = p_stand_id) END     AS status;
END$$

-- -----------------------------------------------------------------------------
-- sp_reservering_overzicht
-- Alle gereserveerde stands van één verkoper (de standhouder), met de
-- gegevens van het evenement erbij voor de pagina 'Mijn reserveringen'.
-- -----------------------------------------------------------------------------
CREATE PROCEDURE sp_reservering_overzicht(IN p_verkoper_id INT UNSIGNED)
BEGIN
    SELECT
        s.id,
        s.evenement_id,
        e.naam                     AS evenement_naam,
        e.datum                    AS evenement_datum,
        e.locatie                  AS evenement_locatie,
        s.stand_type,
        s.prijs,
        s.verhuurd_status,
        s.opmerking,
        s.datum_aangemaakt,
        'Gereserveerd'             AS status,
        v.naam                     AS verkoper_naam
    FROM stand s
    INNER JOIN evenement e
        ON e.id = s.evenement_id
       AND e.is_actief = 1
    LEFT JOIN verkoper v
        ON v.id = s.verkoper_id
    WHERE s.is_actief = 1
      AND s.verhuurd_status = 1
      AND p_verkoper_id > 0
      AND s.verkoper_id = p_verkoper_id
    ORDER BY e.datum ASC, s.id ASC;
END$$

DELIMITER ;

-- -----------------------------------------------------------------------------
-- sp_verkoper_toevoegen
-- Slaat een nieuwe verkoper op met bijbehorend contactpersoon (Sprint 2: create).
--
-- Verkoper en contactpersoon worden in één transactie weggeschreven, zodat er
-- nooit een verkoper zonder bereikbaar contactpersoon ontstaat. Wanneer een
-- insert mislukt wordt alles teruggedraaid (ROLLBACK) en komt de fout via
-- RESIGNAL bij de applicatie terecht; die toont dan de unhappy melding.
--
-- Retourneert het automatisch gegenereerde verkopernummer (id).
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_verkoper_toevoegen;

DELIMITER $$

CREATE PROCEDURE sp_verkoper_toevoegen(
    IN p_naam            VARCHAR(120),
    IN p_speciale_status TINYINT,
    IN p_emailadres      VARCHAR(150),
    IN p_telefoonnummer  VARCHAR(25)
)
BEGIN
    DECLARE v_verkoper_id       INT UNSIGNED;
    DECLARE v_contactpersoon_id INT UNSIGNED;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    INSERT INTO verkoper (naam, speciale_status, verkoopt_soort, stand_type, dagen)
    VALUES (p_naam, p_speciale_status, 'Sneakers', 'A', 1);

    SET v_verkoper_id = LAST_INSERT_ID();

    INSERT INTO contactpersoon (naam, telefoonnummer, emailadres)
    VALUES (p_naam, p_telefoonnummer, p_emailadres);

    SET v_contactpersoon_id = LAST_INSERT_ID();

    INSERT INTO contact_per_verkoper (verkoper_id, contactpersoon_id, is_actief)
    VALUES (v_verkoper_id, v_contactpersoon_id, 1);

    COMMIT;

    SELECT v_verkoper_id AS verkoper_id;
END$$

DELIMITER ;
