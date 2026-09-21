-- =============================================================================
-- Sneakerness(R) - Testdata (seed)
-- -----------------------------------------------------------------------------
-- Vult de database met realistische demodata zodat de read-schermen van
-- Sprint 1 getoond kunnen worden tijdens de sprintreview.
-- =============================================================================

USE sneakerness;

-- Legen in omgekeerde volgorde van de foreign keys.
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE contact_per_verkoper;
TRUNCATE TABLE contactpersoon;
TRUNCATE TABLE ticket;
TRUNCATE TABLE stand;
TRUNCATE TABLE verkoper;
TRUNCATE TABLE prijs;
TRUNCATE TABLE evenement;
TRUNCATE TABLE bezoeker;
TRUNCATE TABLE organisator;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Organisatoren
-- Wachtwoord van beide accounts is 'Sneakerness2026!' (bcrypt-hash).
-- -----------------------------------------------------------------------------
INSERT INTO organisator (naam, gebruikersnaam, wachtwoord, opmerking) VALUES
    ('Noah de Vries', 'noah',  '$2y$10$e0NRPmFHoCV9nZJ0mO2s6uCu9G0k8b5aJm0bQ5m6l6QAr8B5r6J8S', 'Hoofdorganisator Rotterdam'),
    ('Ilse Bakker',  'ilse',  '$2y$10$e0NRPmFHoCV9nZJ0mO2s6uCu9G0k8b5aJm0bQ5m6l6QAr8B5r6J8S', 'Verantwoordelijk voor standverhuur');

-- -----------------------------------------------------------------------------
-- Evenementen (Europese tour)
-- -----------------------------------------------------------------------------
INSERT INTO evenement (naam, datum, locatie, aantal_tickets_per_tijdslot, beschikbare_stands, opmerking) VALUES
    ('Sneakerness Zürich 2026',    '2026-10-03', 'Halle 550, Zürich',            180, 24, 'Tweedaagse editie in Zwitserland'),
    ('Sneakerness Rotterdam 2026', '2026-11-14', 'Van Nellefabriek, Rotterdam',  250, 30, 'Hoofdeditie: tweedaags in de Van Nellefabriek'),
    ('Sneakerness Köln 2026',      '2026-12-05', 'Xpost, Köln',                  200, 24, 'Tweedaagse editie in Duitsland'),
    ('Sneakerness Milano 2027',    '2027-03-20', 'Superstudio Più, Milano',      220, 20, 'Voorjaarseditie Italië'),
    ('Sneakerness Wien 2027',      '2027-05-15', 'Marx Halle, Wien',             190, 20, 'Afsluiter van het seizoen');

SET @zurich    = (SELECT id FROM evenement WHERE naam = 'Sneakerness Zürich 2026');
SET @rotterdam = (SELECT id FROM evenement WHERE naam = 'Sneakerness Rotterdam 2026');
SET @koln      = (SELECT id FROM evenement WHERE naam = 'Sneakerness Köln 2026');
SET @milano    = (SELECT id FROM evenement WHERE naam = 'Sneakerness Milano 2027');
SET @wien      = (SELECT id FROM evenement WHERE naam = 'Sneakerness Wien 2027');

-- -----------------------------------------------------------------------------
-- Prijzen / tijdsloten
-- Vroege toegang is duurder: wie als eerste binnen is, heeft de beste kans
-- op de zeldzame paren.
-- -----------------------------------------------------------------------------
INSERT INTO prijs (evenement_id, datum, tijdslot, tarief, opmerking) VALUES
    -- Zürich, dag 1 en dag 2
    (@zurich, '2026-10-03', '10:00:00', 35.00, 'Early access'),
    (@zurich, '2026-10-03', '12:00:00', 22.50, NULL),
    (@zurich, '2026-10-03', '14:00:00', 17.50, NULL),
    (@zurich, '2026-10-04', '11:00:00', 25.00, NULL),
    (@zurich, '2026-10-04', '14:00:00', 15.00, 'Late entry'),
    -- Rotterdam, dag 1
    (@rotterdam, '2026-11-14', '10:00:00', 39.50, 'Early access: eerste keuze uit alle drops'),
    (@rotterdam, '2026-11-14', '11:00:00', 29.50, NULL),
    (@rotterdam, '2026-11-14', '12:00:00', 24.50, NULL),
    (@rotterdam, '2026-11-14', '14:00:00', 19.50, NULL),
    (@rotterdam, '2026-11-14', '16:00:00', 14.50, 'Late entry'),
    -- Rotterdam, dag 2
    (@rotterdam, '2026-11-15', '10:00:00', 34.50, 'Early access dag 2'),
    (@rotterdam, '2026-11-15', '11:00:00', 27.50, NULL),
    (@rotterdam, '2026-11-15', '12:00:00', 22.50, NULL),
    (@rotterdam, '2026-11-15', '14:00:00', 17.50, NULL),
    (@rotterdam, '2026-11-15', '16:00:00', 12.50, 'Late entry'),
    -- Köln
    (@koln, '2026-12-05', '10:00:00', 32.00, 'Early access'),
    (@koln, '2026-12-05', '12:00:00', 21.00, NULL),
    (@koln, '2026-12-06', '11:00:00', 24.00, NULL),
    (@koln, '2026-12-06', '14:00:00', 16.00, 'Late entry'),
    -- Milano
    (@milano, '2027-03-20', '10:00:00', 33.00, 'Early access'),
    (@milano, '2027-03-20', '13:00:00', 20.00, NULL),
    (@milano, '2027-03-21', '11:00:00', 23.00, NULL),
    -- Wien
    (@wien, '2027-05-15', '10:00:00', 30.00, 'Early access'),
    (@wien, '2027-05-15', '13:00:00', 19.00, NULL),
    (@wien, '2027-05-16', '11:00:00', 22.00, NULL);

-- -----------------------------------------------------------------------------
-- Verkopers: shops, privéverkopers, partners en side-stands
-- -----------------------------------------------------------------------------
INSERT INTO verkoper (naam, speciale_status, verkoopt_soort, stand_type, dagen, logo, beschrijving) VALUES
    ('Kickz Kollektiv',       1, 'Sneakers',        'AA+', 2, 'kickz-kollektiv.svg', 'Rotterdamse shop gespecialiseerd in deadstock Jordans en zeldzame Dunks. Elke editie met een exclusieve in-store drop.'),
    ('Sole Sisters',          1, 'Sneakers',        'AA+', 2, 'sole-sisters.svg',    'Community-platform voor vrouwen in sneakercultuur. Op de stand: archive-runners en een doorlopend talkprogramma.'),
    ('Crep Care',             1, 'Lifestyle',       'AA',  2, 'crep-care.svg',       'Cleaning bar waar je je paren gratis laat reinigen terwijl je shopt.'),
    ('Dope Dutch Kicks',      0, 'Sneakers',        'AA',  2, NULL, NULL),
    ('010 Grails',            0, 'Sneakers',        'AA',  1, NULL, NULL),
    ('Archive Runners',       0, 'Sneakers',        'A',   1, NULL, NULL),
    ('Vintage Sole Market',   0, 'Sneakers',        'A',   2, NULL, NULL),
    ('Hype Trade Rotterdam',  0, 'Sneakers',        'A',   1, NULL, NULL),
    ('Kapsalon Karim',        0, 'Eten en Drinken', 'A',   2, NULL, 'Streetfood met een Rotterdamse twist.'),
    ('Barista Bros',          0, 'Eten en Drinken', 'A',   2, NULL, 'Specialty coffee voor de vroege vogels in het early access slot.'),
    ('Little Kicks Corner',   0, 'Kids Corner',     'A',   2, NULL, 'Speelhoek en mini-customizing voor kinderen tot 12 jaar.'),
    ('Custom Lab 010',        1, 'Customizer',      'AA',  2, 'custom-lab-010.svg',  'Live customizing: laat je paar ter plekke voorzien van hand-painted artwork.'),
    ('Ink & Sole Tattoo',     0, 'Tattoo',          'AA',  2, NULL, 'Walk-in flash tattoos met sneaker- en street-art motieven.'),
    ('Fade Factory',          0, 'Barbershop',      'A',   2, NULL, 'Fresh fade terwijl je wacht op de raffle-uitslag.'),
    ('DJ Rollin 010',         0, 'DJ-set',          'A',   2, NULL, 'Doorlopende DJ-sets: hiphop, garage en Rotterdamse classics.'),
    ('Zürich Sneaker Society', 0, 'Sneakers',       'AA',  2, NULL, NULL),
    ('Köln Kicks Club',       0, 'Sneakers',        'AA',  2, NULL, NULL);

-- -----------------------------------------------------------------------------
-- Stands per evenement
-- De stands worden gegenereerd met een recursieve CTE: een AA+ stand staat
-- vooraan met de meeste vierkante meters, een A stand is het instapmodel.
-- -----------------------------------------------------------------------------
INSERT INTO stand (evenement_id, stand_type, prijs, verhuurd_status)
WITH RECURSIVE nummers AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM nummers WHERE n < 30
)
SELECT
    @rotterdam,
    CASE WHEN n <= 6 THEN 'AA+' WHEN n <= 16 THEN 'AA' ELSE 'A' END,
    CASE WHEN n <= 6 THEN 995.00 WHEN n <= 16 THEN 745.00 ELSE 495.00 END,
    0
FROM nummers;

INSERT INTO stand (evenement_id, stand_type, prijs, verhuurd_status)
WITH RECURSIVE nummers AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM nummers WHERE n < 24
)
SELECT
    @zurich,
    CASE WHEN n <= 4 THEN 'AA+' WHEN n <= 12 THEN 'AA' ELSE 'A' END,
    CASE WHEN n <= 4 THEN 920.00 WHEN n <= 12 THEN 690.00 ELSE 460.00 END,
    0
FROM nummers;

INSERT INTO stand (evenement_id, stand_type, prijs, verhuurd_status)
WITH RECURSIVE nummers AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM nummers WHERE n < 24
)
SELECT
    @koln,
    CASE WHEN n <= 4 THEN 'AA+' WHEN n <= 12 THEN 'AA' ELSE 'A' END,
    CASE WHEN n <= 4 THEN 940.00 WHEN n <= 12 THEN 700.00 ELSE 470.00 END,
    0
FROM nummers;

INSERT INTO stand (evenement_id, stand_type, prijs, verhuurd_status)
WITH RECURSIVE nummers AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM nummers WHERE n < 20
)
SELECT
    @milano,
    CASE WHEN n <= 4 THEN 'AA+' WHEN n <= 10 THEN 'AA' ELSE 'A' END,
    CASE WHEN n <= 4 THEN 960.00 WHEN n <= 10 THEN 720.00 ELSE 480.00 END,
    0
FROM nummers;

INSERT INTO stand (evenement_id, stand_type, prijs, verhuurd_status)
WITH RECURSIVE nummers AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM nummers WHERE n < 20
)
SELECT
    @wien,
    CASE WHEN n <= 3 THEN 'AA+' WHEN n <= 10 THEN 'AA' ELSE 'A' END,
    CASE WHEN n <= 3 THEN 910.00 WHEN n <= 10 THEN 680.00 ELSE 455.00 END,
    0
FROM nummers;

-- -----------------------------------------------------------------------------
-- Stands koppelen aan verkopers (verhuurd)
-- Elke verkoper krijgt de eerste vrije stand van het juiste type.
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_seed_verhuur_stand;
DELIMITER $$
CREATE PROCEDURE sp_seed_verhuur_stand(
    IN p_evenement_id INT UNSIGNED,
    IN p_verkoper_naam VARCHAR(120),
    IN p_stand_type VARCHAR(3)
)
BEGIN
    DECLARE v_verkoper_id INT UNSIGNED;
    DECLARE v_stand_id INT UNSIGNED;

    SELECT id INTO v_verkoper_id FROM verkoper WHERE naam = p_verkoper_naam LIMIT 1;

    SELECT id INTO v_stand_id
    FROM stand
    WHERE evenement_id = p_evenement_id
      AND stand_type = p_stand_type
      AND verhuurd_status = 0
    ORDER BY id
    LIMIT 1;

    IF v_verkoper_id IS NOT NULL AND v_stand_id IS NOT NULL THEN
        UPDATE stand
        SET verkoper_id = v_verkoper_id,
            verhuurd_status = 1
        WHERE id = v_stand_id;
    END IF;
END$$
DELIMITER ;

CALL sp_seed_verhuur_stand(@rotterdam, 'Kickz Kollektiv',      'AA+');
CALL sp_seed_verhuur_stand(@rotterdam, 'Sole Sisters',         'AA+');
CALL sp_seed_verhuur_stand(@rotterdam, 'Crep Care',            'AA');
CALL sp_seed_verhuur_stand(@rotterdam, 'Dope Dutch Kicks',     'AA');
CALL sp_seed_verhuur_stand(@rotterdam, '010 Grails',           'AA');
CALL sp_seed_verhuur_stand(@rotterdam, 'Custom Lab 010',       'AA');
CALL sp_seed_verhuur_stand(@rotterdam, 'Ink & Sole Tattoo',    'AA');
CALL sp_seed_verhuur_stand(@rotterdam, 'Archive Runners',      'A');
CALL sp_seed_verhuur_stand(@rotterdam, 'Vintage Sole Market',  'A');
CALL sp_seed_verhuur_stand(@rotterdam, 'Hype Trade Rotterdam', 'A');
CALL sp_seed_verhuur_stand(@rotterdam, 'Kapsalon Karim',       'A');
CALL sp_seed_verhuur_stand(@rotterdam, 'Barista Bros',         'A');
CALL sp_seed_verhuur_stand(@rotterdam, 'Little Kicks Corner',  'A');
CALL sp_seed_verhuur_stand(@rotterdam, 'Fade Factory',         'A');
CALL sp_seed_verhuur_stand(@rotterdam, 'DJ Rollin 010',        'A');

CALL sp_seed_verhuur_stand(@zurich, 'Kickz Kollektiv',        'AA+');
CALL sp_seed_verhuur_stand(@zurich, 'Zürich Sneaker Society', 'AA');
CALL sp_seed_verhuur_stand(@zurich, 'Crep Care',              'AA');
CALL sp_seed_verhuur_stand(@zurich, 'DJ Rollin 010',          'A');

CALL sp_seed_verhuur_stand(@koln, 'Köln Kicks Club', 'AA+');
CALL sp_seed_verhuur_stand(@koln, 'Crep Care',       'AA');
CALL sp_seed_verhuur_stand(@koln, 'Barista Bros',    'A');

CALL sp_seed_verhuur_stand(@milano, 'Sole Sisters',   'AA+');
CALL sp_seed_verhuur_stand(@milano, 'Custom Lab 010', 'AA');

CALL sp_seed_verhuur_stand(@wien, 'Kickz Kollektiv', 'AA+');

DROP PROCEDURE IF EXISTS sp_seed_verhuur_stand;

-- -----------------------------------------------------------------------------
-- Contactpersonen van verkopers
-- -----------------------------------------------------------------------------
INSERT INTO contactpersoon (naam, telefoonnummer, emailadres) VALUES
    ('Samir el Idrissi', '+31 6 12345678', 'samir@kickzkollektiv.nl'),
    ('Fleur Jansen',     '+31 6 23456789', 'fleur@kickzkollektiv.nl'),
    ('Amber de Groot',   '+31 6 34567890', 'amber@solesisters.eu'),
    ('Tim Verhoeven',    '+31 6 45678901', 'tim@crepcare.com'),
    ('Yara Mensah',      '+31 6 56789012', 'yara@customlab010.nl'),
    ('Karim Bouazza',    '+31 6 67890123', 'karim@kapsalonkarim.nl');

INSERT INTO contact_per_verkoper (verkoper_id, contactpersoon_id, opmerking)
SELECT v.id, c.id, k.opmerking
FROM (
    SELECT 'Kickz Kollektiv' AS verkoper, 'Samir el Idrissi' AS contact, 'Hoofdcontact'          AS opmerking UNION ALL
    SELECT 'Kickz Kollektiv',            'Fleur Jansen',                'Logistiek en opbouw'   UNION ALL
    SELECT 'Sole Sisters',               'Amber de Groot',              'Hoofdcontact'          UNION ALL
    SELECT 'Crep Care',                  'Tim Verhoeven',               'Hoofdcontact'          UNION ALL
    SELECT 'Custom Lab 010',             'Yara Mensah',                 'Hoofdcontact'          UNION ALL
    SELECT 'Kapsalon Karim',             'Karim Bouazza',               'Hoofdcontact'
) AS k
INNER JOIN verkoper v       ON v.naam = k.verkoper
INNER JOIN contactpersoon c ON c.naam = k.contact;

-- -----------------------------------------------------------------------------
-- Bezoekers en verkochte tickets
-- -----------------------------------------------------------------------------
INSERT INTO bezoeker (naam, emailadres) VALUES
    ('Milan Pieters',   'milan.pieters@example.com'),
    ('Fatima Aydin',    'fatima.aydin@example.com'),
    ('Jesse van Dijk',  'jesse.vandijk@example.com'),
    ('Nora Willems',    'nora.willems@example.com'),
    ('Ravi Sharma',     'ravi.sharma@example.com'),
    ('Lotte Smeets',    'lotte.smeets@example.com');

-- Tickets koppelen aan bestaande tijdsloten via een JOIN op prijs.
INSERT INTO ticket (bezoeker_id, evenement_id, prijs_id, aantal_tickets, datum, opmerking)
SELECT b.id, p.evenement_id, p.id, d.aantal, p.datum, d.opmerking
FROM (
    SELECT 'Milan Pieters'  AS bezoeker, '2026-11-14' AS datum, '10:00:00' AS tijdslot, 2 AS aantal, 'Early access'     AS opmerking UNION ALL
    SELECT 'Fatima Aydin',  '2026-11-14', '10:00:00', 1, NULL UNION ALL
    SELECT 'Jesse van Dijk', '2026-11-14', '11:00:00', 3, 'Komt met vrienden' UNION ALL
    SELECT 'Nora Willems',  '2026-11-14', '12:00:00', 2, NULL UNION ALL
    SELECT 'Ravi Sharma',   '2026-11-14', '14:00:00', 1, NULL UNION ALL
    SELECT 'Lotte Smeets',  '2026-11-15', '10:00:00', 2, 'Tweede dag' UNION ALL
    SELECT 'Milan Pieters', '2026-11-15', '11:00:00', 1, NULL UNION ALL
    SELECT 'Fatima Aydin',  '2026-10-03', '10:00:00', 2, 'Zürich' UNION ALL
    SELECT 'Jesse van Dijk', '2026-10-03', '12:00:00', 1, NULL UNION ALL
    SELECT 'Nora Willems',  '2026-12-05', '10:00:00', 4, 'Köln met de groep'
) AS d
INNER JOIN bezoeker b ON b.naam = d.bezoeker
INNER JOIN prijs p    ON p.datum = d.datum AND p.tijdslot = d.tijdslot;
