# Project-L2-p1

Sneakerness® Rotterdam — Sprint 1 (read-functionaliteit)

## Setup

1. Database instellen:
   ```
   php database/install.php
   ```
2. Start de PHP ontwikkelserver:
   ```
   cd public
   php -S 127.0.0.1:8000 router.php
   ```
   Vanaf de projectroot werkt dit ook:
   ```
   php -S 127.0.0.1:8000 server.php
   ```
3. Open http://127.0.0.1:8000/

## Testen

- **Happy flow** (content tonen):
  ```
  php database/install.php
  ```
- **Unhappy flow** (melding tonen):
  ```
  php database/delete-event.php
  ```
- Na elke wissel: **Ctrl+F5** op de page.

## Verkopersoverzicht

Route: `/verkopers` — alle shops en privéverkopers met een stand op een actief
evenement, op basis van de stored procedure `sp_verkoper_overzicht`.

- **Filter op soort** via de knoppen bovenaan, bijvoorbeeld
  `/verkopers?soort=Sneakers`.
- **Zoeken op naam** via het zoekveld, bijvoorbeeld
  `/verkopers?zoek=Kickz`.
- Beide combineren kan: `/verkopers?soort=Sneakers&zoek=Rotterdam`.
- Vanaf een kaart gaat de link naar de contactpersonen van die verkoper:
  `/verkopers/{id}/contact`.

Unhappy flows op deze pagina:

| Situatie | Melding |
| --- | --- |
| Nog geen verkopers | "Er zijn nog geen verkopers bekend voor de komende editie." |
| Filter zonder resultaat | "Er zijn geen verkopers bekend die "…" verkopen." |
| Zoekopdracht zonder resultaat | "Er zijn geen gevonden voor "…"." |
| Database niet bereikbaar | "De gegevens konden niet worden opgehaald." (HTTP 503) |

## Ticketpagina: tijdsloten

Route: `/tickets` — kies een editie in het dropdown-veld en klik op
**"Toon tijdsloten"**.

**Happy flow:** per dag een blok (`dag-kop`) met daarin per tijdslot de
starttijd, de prijs en het aantal beschikbare tickets ten opzichte van het
totaal (`X van Y tickets beschikbaar`). De gegevens komen uit
`sp_tijdslot_overzicht`; de capaciteit is `evenement.aantal_tickets_per_tijdslot`.

**Unhappy flows:**

| Situatie | Melding |
| --- | --- |
| Gekozen editie heeft geen tijdsloten | "Er zijn momenteel geen tijdsloten beschikbaar." |
| Nog geen evenementen aangemaakt | "Er zijn nog geen events aangemaakt." |
| Onbekend event gekozen | "Het gekozen event is niet gevonden. Kies hieronder een van de geplande edities." |
| Database niet bereikbaar | "Onze excuses, er is een error. We zijn ermee bezig." (HTTP 503) |

Zonder tijdsloten worden er géén kaarten getoond, alleen de melding hierboven.

### Happy / unhappy wisselen

```
php database/demo_unhappy.php uit tijdsloten 4    # geen tijdsloten voor editie 4
php database/demo_unhappy.php uit tijdsloten      # voor alle edities
php database/demo_unhappy.php aan tijdsloten 4    # weer terug (happy flow)
```

De scenario's zijn ook geautomatiseerd te controleren:

```
php database/test-tijdsloten.php
```

## Structuur

- `app/` — MVC code (Controllers, Models, Views, Core)
- `config/` — configuratie (database credentials)
- `database/` — SQL schema, stored procedures, seed data
- `public/` — front controller, assets, router
- `storage/` — logs
