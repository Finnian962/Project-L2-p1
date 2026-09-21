-- =============================================================================
-- Sneakerness(R) - Databaseschema (Sprint 1)
-- -----------------------------------------------------------------------------
-- Dit script maakt de database en alle tabellen aan volgens het ERD
-- (zie docs/ERD.md). Alle tabellen gebruiken InnoDB zodat foreign keys
-- daadwerkelijk worden afgedwongen.
--
-- Standaardkolommen (op elke entiteit, conform de opdracht):
--   is_actief        -> soft delete / actief-vlag
--   opmerking        -> vrije notitie voor de organisator
--   datum_aangemaakt -> tijdstip van aanmaken
--   datum_gewijzigd  -> tijdstip van laatste wijziging
-- =============================================================================

DROP DATABASE IF EXISTS sneakerness;
CREATE DATABASE sneakerness
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sneakerness;

-- -----------------------------------------------------------------------------
-- Tabel: organisator
-- De beheerder van het evenement (beheert events, tickets en stands).
-- -----------------------------------------------------------------------------
CREATE TABLE organisator (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam             VARCHAR(100) NOT NULL,
    gebruikersnaam   VARCHAR(50)  NOT NULL,
    wachtwoord       VARCHAR(255) NOT NULL COMMENT 'Hash (password_hash), nooit plain tekst',
    is_actief        TINYINT(1)   NOT NULL DEFAULT 1,
    opmerking        VARCHAR(255)     NULL,
    datum_aangemaakt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_organisator_gebruikersnaam (gebruikersnaam)
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabel: bezoeker
-- Koper van een of meerdere tickets.
-- -----------------------------------------------------------------------------
CREATE TABLE bezoeker (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam             VARCHAR(100) NOT NULL,
    emailadres       VARCHAR(150) NOT NULL,
    is_actief        TINYINT(1)   NOT NULL DEFAULT 1,
    opmerking        VARCHAR(255)     NULL,
    datum_aangemaakt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_bezoeker_emailadres (emailadres)
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabel: evenement
-- Een editie van Sneakerness (bijv. Sneakerness Rotterdam 2026).
-- -----------------------------------------------------------------------------
CREATE TABLE evenement (
    id                           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam                         VARCHAR(150) NOT NULL,
    datum                        DATE         NOT NULL COMMENT 'Startdatum van de editie',
    locatie                      VARCHAR(150) NOT NULL,
    aantal_tickets_per_tijdslot  INT UNSIGNED NOT NULL DEFAULT 0,
    beschikbare_stands           INT UNSIGNED NOT NULL DEFAULT 0,
    is_actief                    TINYINT(1)   NOT NULL DEFAULT 1,
    opmerking                    VARCHAR(255)     NULL,
    datum_aangemaakt             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_evenement_datum (datum)
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabel: prijs
-- Een tijdslot met tarief op een specifieke dag van een evenement.
-- Vroege toegang is duurder dan late toegang.
-- evenement_id is een toevoeging op de basisentiteit zodat tijdsloten
-- aan de juiste editie gekoppeld kunnen worden (zie docs/ERD.md).
-- -----------------------------------------------------------------------------
CREATE TABLE prijs (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    evenement_id     INT UNSIGNED  NOT NULL,
    datum            DATE          NOT NULL,
    tijdslot         TIME          NOT NULL COMMENT 'Toegangstijd, bijv. 11:00',
    tarief           DECIMAL(6, 2) NOT NULL,
    is_actief        TINYINT(1)    NOT NULL DEFAULT 1,
    opmerking        VARCHAR(255)      NULL,
    datum_aangemaakt DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prijs_slot (evenement_id, datum, tijdslot),
    CONSTRAINT fk_prijs_evenement
        FOREIGN KEY (evenement_id) REFERENCES evenement (id)
        ON DELETE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabel: ticket
-- Een boeking van een bezoeker voor een tijdslot van een evenement.
-- -----------------------------------------------------------------------------
CREATE TABLE ticket (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bezoeker_id      INT UNSIGNED NOT NULL,
    evenement_id     INT UNSIGNED NOT NULL,
    prijs_id         INT UNSIGNED NOT NULL,
    aantal_tickets   INT UNSIGNED NOT NULL DEFAULT 1,
    datum            DATE         NOT NULL COMMENT 'Bezoekdatum',
    is_actief        TINYINT(1)   NOT NULL DEFAULT 1,
    opmerking        VARCHAR(255)     NULL,
    datum_aangemaakt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ticket_bezoeker
        FOREIGN KEY (bezoeker_id) REFERENCES bezoeker (id),
    CONSTRAINT fk_ticket_evenement
        FOREIGN KEY (evenement_id) REFERENCES evenement (id),
    CONSTRAINT fk_ticket_prijs
        FOREIGN KEY (prijs_id) REFERENCES prijs (id)
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabel: verkoper
-- Shop, privéverkoper, partner of side-stand (eten, tattoo, barber, DJ, ...).
-- -----------------------------------------------------------------------------
CREATE TABLE verkoper (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam             VARCHAR(120) NOT NULL,
    speciale_status  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = partner met logo en extra info',
    verkoopt_soort   VARCHAR(60)  NOT NULL COMMENT 'Sneakers, Eten en Drinken, Kids Corner, ...',
    stand_type       ENUM('A', 'AA', 'AA+') NOT NULL DEFAULT 'A',
    dagen            TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = één dag, 2 = beide dagen',
    logo             VARCHAR(150)     NULL COMMENT 'Bestandsnaam logo, alleen voor partners',
    beschrijving     TEXT             NULL COMMENT 'Extra informatie van partners',
    is_actief        TINYINT(1)   NOT NULL DEFAULT 1,
    opmerking        VARCHAR(255)     NULL,
    datum_aangemaakt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_verkoper_soort (verkoopt_soort)
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabel: stand
-- Een fysieke standplaats op een evenement, eventueel verhuurd aan een verkoper.
-- evenement_id is een toevoeging op de basisentiteit zodat stands per editie
-- beheerd kunnen worden (zie docs/ERD.md).
-- -----------------------------------------------------------------------------
CREATE TABLE stand (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    evenement_id     INT UNSIGNED  NOT NULL,
    verkoper_id      INT UNSIGNED      NULL COMMENT 'NULL zolang de stand vrij is',
    stand_type       ENUM('A', 'AA', 'AA+') NOT NULL,
    prijs            DECIMAL(8, 2) NOT NULL,
    verhuurd_status  TINYINT(1)    NOT NULL DEFAULT 0,
    is_actief        TINYINT(1)    NOT NULL DEFAULT 1,
    opmerking        VARCHAR(255)      NULL,
    datum_aangemaakt DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_stand_evenement
        FOREIGN KEY (evenement_id) REFERENCES evenement (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_stand_verkoper
        FOREIGN KEY (verkoper_id) REFERENCES verkoper (id)
        ON DELETE SET NULL
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabel: contactpersoon
-- Contactgegevens waarop de organisator een verkoper kan bereiken.
-- -----------------------------------------------------------------------------
CREATE TABLE contactpersoon (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    naam             VARCHAR(100) NOT NULL,
    telefoonnummer   VARCHAR(25)  NOT NULL,
    emailadres       VARCHAR(150) NOT NULL,
    is_actief        TINYINT(1)   NOT NULL DEFAULT 1,
    opmerking        VARCHAR(255)     NULL,
    datum_aangemaakt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabel: contact_per_verkoper
-- Koppeltabel: een verkoper kan meerdere contactpersonen hebben.
-- -----------------------------------------------------------------------------
CREATE TABLE contact_per_verkoper (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    verkoper_id       INT UNSIGNED NOT NULL,
    contactpersoon_id INT UNSIGNED NOT NULL,
    is_actief         TINYINT(1)   NOT NULL DEFAULT 1,
    opmerking         VARCHAR(255)     NULL,
    datum_aangemaakt  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_gewijzigd   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contact_per_verkoper (verkoper_id, contactpersoon_id),
    CONSTRAINT fk_cpv_verkoper
        FOREIGN KEY (verkoper_id) REFERENCES verkoper (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cpv_contactpersoon
        FOREIGN KEY (contactpersoon_id) REFERENCES contactpersoon (id)
        ON DELETE CASCADE
) ENGINE = InnoDB;
