# Project-L2-p1

Sneakerness® Rotterdam — publieke site, tickets, verkopers en inloggen met rollen

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

## Inloggen (rollen)

Route: `/inloggen` — gebruikersnaam en wachtwoord. Na een geslaagde inlog komt
de naam van de gebruiker met een knop **Uitloggen** in het navigatiemenu te
staan, en de standhouder krijgt bovendien het menu-item *Reserveringen*.

| Rol | Gebruikersnaam | Wachtwoord | Toegang tot |
|---|---|---|---|
| Beheerder | `noah`, `ilse` | `Sneakerness2026!` | `/beheer`, `/beheer/events/toevoegen`, `/beheer/stands`, `/beheer/stands/toevoegen`, `/beheer/log` |
| Standhouder | `kickz`, `sole`, `crep` | `Sneakerness2026!` | `/stands/beschikbaar`, `/reserveringen` |

- Wie al ingelogd is en `/inloggen` bezoekt, gaat direct naar de homepagina en
  krijgt het formulier niet te zien.
- Niet-ingelogde bezoekers van een afgeschermde pagina worden doorgestuurd naar
  `/inloggen`; ingelogde gebruikers met de verkeerde rol krijgen "Geen toegang"
  (403) te zien.

Scenarios:

```bash
# verkeerde gebruikersnaam/wachtwoord → melding op het formulier (401)
# database niet bereikbaar            → melding op het formulier (503)
```

## Stand toevoegen (beheerder)

Route: `/beheer/stands/toevoegen` (knop **Stand toevoegen** op
`/beheer/stands`), opgeslagen via `sp_stand_toevoegen`.

- **Happy flow**: editie, standtype (A, AA of AA+), prijs en een optionele
  opmerking invullen → knop **Opslaan** → melding *"De stand is succesvol
  toegevoegd."* → doorverwijzing naar `/beheer/stands`, waar de nieuwe stand
  met status *Beschikbaar* staat. De stand verschijnt meteen ook in het aanbod
  op `/stands`.

```bash
# ongeldige invoer (422): prijs "abc" invullen en opslaan
# geen evenementen (422):  php database/demo_unhappy.php uit events
# databasefout (503):      php database/demo_unhappy.php uit stands
```

## Stand reserveren (standhouder)

Routes: `/stands/beschikbaar` (overzicht), POST `/stands/reserveren` en
`/reserveringen` (eigen reserveringen), via `sp_stand_beschikbaar`,
`sp_stand_reserveren` en `sp_reservering_overzicht`.

- **Happy flow**: inloggen als `kickz` → `/stands/beschikbaar` → knop
  **Reserveren** → melding *"De stand is succesvol gereserveerd."* →
  `/reserveringen` toont de stand met status *Gereserveerd* en de
  reservatiedatum. In het beheeroverzicht staat dezelfde stand nu ook als
  *Gereserveerd*, met de naam van de standhouder erbij.

```bash
# geen stand gekozen           → melding op /stands/beschikbaar
# net vergeven (2e reservering)→ "Deze stand is net door iemand anders gereserveerd."
# account zonder verkoper      → melding op /reserveringen
# databasefout (503):          php database/demo_unhappy.php uit stands
```

### Beide ongelukkige scenario's laten zien

De databasefout is in de browser niet te veroorzaken, dus die zet je met een
commando aan. Hetzelfde commando blokkeert ook het toevoegen van een stand:

```
php database/demo_unhappy.php uit stands    # beide handelingen falen nu
php database/demo_unhappy.php aan stands    # beide werken weer normaal
```

Tussen die twee commando's in probeer je als `noah` een stand op te slaan en als
`kickz` een stand te reserveren. Beide keren verschijnt de rode melding ("De
stand is niet opgeslagen" / "De reservering kon niet worden opgeslagen"),
blijven de ingevulde waarden in het formulier staan en wordt er niets
geschreven: het standenoverzicht telt niet omhoog en de stand blijft
*Beschikbaar*.

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

## Verkoper toevoegen (medewerker)

Deel van de user story *"Als medewerker wil ik een nieuwe verkoper kunnen
toevoegen"*, bereikbaar vanaf de knop **Verkoper toevoegen** in de kop van de
beheerpagina (`/beheer`).

| | |
| --- | --- |
| `GET /verkopers/nieuw` | toont het formulier met de velden **naam**, **status** (gewone verkoper of partner), **e-mailadres** en **telefoonnummer** |
| `POST /verkopers/nieuw` | slaat de ingevulde verkoper op |

De opslag loopt via de stored procedure `sp_verkoper_toevoegen`: verkoper,
contactpersoon en de koppeling daartussen worden in één transactie
weggeschreven, zodat er nooit een verkoper zonder bereikbaar contactpersoon
ontstaat. Wanneer één van de drie inserts faalt wordt alles teruggedraaid
(`ROLLBACK`) en komt de fout bij de applicatie terecht. Het verkopernummer is
de auto_increment `verkoper.id` en wordt dus automatisch gegenereerd.

Na een succesvolle opslag volgt een doorverwijzing (Post/Redirect/Get) naar
`/verkopers?toegevoegd={id}`. Zo toont het overzicht meteen de nieuwe verkoper
én is het verversen van de pagina geen tweede keer opslaan.

Een nieuwe verkoper heeft nog geen stand: daarom toont `sp_verkoper_overzicht`
stands met een `LEFT JOIN` en zie je op de kaart geen stand-badges, alleen
naam, soort, status en het aantal contactpersonen.

### Het happy flow laten zien

1. Open `/beheer` en klik op **Verkoper toevoegen**.
2. Vul naam, status, e-mailadres en telefoonnummer in en klik **Opslaan**.
3. Je staat op `/verkopers` met de melding **"De verkoper is succesvol
   toegevoegd."** en de nieuwe verkoper als kaart in de lijst; de teller is
   één omhoog gegaan.

Unhappy flows op deze pagina:

| Situatie | Melding | Status |
| --- | --- | --- |
| Naam leeg | "Vul een naam in." — erboven "Vul de gemarkeerde verplichte velden in." | 422 |
| E-mailadres of telefoonnummer leeg | "Vul een e-mailadres in." / "Vul een telefoonnummer in." | 422 |
| Naam langer dan 120 tekens | "De naam mag maximaal 120 tekens bevatten." | 422 |
| E-mailadres langer dan 150 tekens | "Het e-mailadres mag maximaal 150 tekens bevatten." | 422 |
| Telefoonnummer langer dan 25 tekens | "Het telefoonnummer mag maximaal 25 tekens bevatten." | 422 |
| Ongeldig e-mailadres | "Vul een geldig e-mailadres in, bijvoorbeeld naam@voorbeeld.nl." | 422 |
| Ongeldige statuswaarde | "Kies een geldige status." | 422 |
| Verkoper met dezelfde naam bestaat al | "Er bestaat al een verkoper met deze naam. Controleer de gegevens of vul een andere naam in." | 409 |
| **Databasefout bij het opslaan** | **"Er is een fout opgetreden bij het opslaan van de verkoper. Probeer het later opnieuw."** | 500 |

Bij elke melding blijven de ingevulde gegevens in het formulier staan en wordt
er niets opgeslagen.

### Het ongelukkige scenario laten zien

De databasefout is niet in de browser te veroorzaken (het naamveld is
maximaal 120 tekens lang), dus die zet je met een commando aan:

```
php database/demo_unhappy.php uit verkoper    # elke opslaan faalt nu
php database/demo_unhappy.php aan verkoper    # weer normaal
```

Tussen die twee commando's in: formulier invullen, **Opslaan** klikken, en je
ziet de rode melding met alle ingevulde waarden nog in de velden.

De hele flow is ook geautomatiseerd te controleren (24 controles; de server
moet draaien):

```
php database/test-verkoper-toevoegen.php
```

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

## Ticket toevoegen (medewerker)

Twee schrijfacties (create) voor de medewerker, allebei bereikbaar vanaf de
ticketpagina `/tickets`.

### 1. Nieuw tijdslot — `/tickets/toevoegen`

Route uit het hoofdmenu. Vul editie, dag, starttijd, tarief en een optionele
opmerking in. Het nieuwe tijdslot wordt opgeslagen in de tabel `prijs` via de
stored procedure `sp_tijdslot_toevoegen` en verschijnt direct als kaartje op
`/tickets` met de beschikbaarheid van de editie
(`evenement.aantal_tickets_per_tijdslot`), zodat er meteen tickets voor
verkocht kunnen worden.

**Happy flow:** opslaan stuurt door naar `/tickets?event={id}&toegevoegd=1`,
waar je de melding "Het ticket is succesvol toegevoegd." ziet én het nieuwe
tijdslot tussen de andere kaartjes. De doorverwijzing voorkomt dat het
verversen van de pagina hetzelfde tijdslot nog een keer opslaat.

Unhappy flows op deze pagina:

| Situatie | Melding |
| --- | --- |
| Geen editie gekozen | "Kies een editie." (HTTP 422) |
| Datum leeg of ongeldig | "Vul een datum in, bijvoorbeeld 14-11-2026." (HTTP 422) |
| Starttijd leeg, ongeldig of buiten 00:00–23:59 | "Vul een starttijd in tussen 00:00 en 23:59, bijvoorbeeld 10:00." (HTTP 422) |
| Tarief leeg, niet-numeriek of nul | "Vul een geldig tarief in, bijvoorbeeld 24.50." (HTTP 422) |
| Tarief boven € 9.999,99 | "Het tarief mag maximaal € 9.999,99 zijn." (HTTP 422) |
| Opmerking langer dan 255 tekens | "De opmerking mag maximaal 255 tekens bevatten." (HTTP 422) |
| Dag vóór de start van de editie | "De datum mag niet voor de start van de editie liggen (vanaf 14 november 2026)." (HTTP 422) |
| Dubbel tijdstip in de editie | "Voor dit tijdstip bestaat al een tijdslot. Kies een andere dag of starttijd." (HTTP 422) |
| Technische fout, bijvoorbeeld database niet bereikbaar | "Het ticket kon niet worden opgeslagen. Probeer het later opnieuw." (HTTP 503) |

### 2. Ticket boeken — `/tickets/boeken`

Elk tijdslot op `/tickets` heeft een knop **Ticket boeken** die deze pagina
opent met `?event={id}&tijdslot={prijsId}`, dus met dat tijdslot al
geselecteerd. Een onbekend of verkeerd `tijdslot`-id valt terug op het eerste
tijdslot van de gekozen editie. Het ticket wordt opgeslagen in de tabel
`ticket` via de stored procedure `sp_ticket_toevoegen` en telt daarna direct
mee in de beschikbaarheid op `/tickets`.

Er hoeft geen bezoeker te zijn: `ticket.bezoeker_id` is optioneel en blijft
`NULL` wanneer een medewerker het ticket toevoegt.

**Happy flow:** opslaan stuurt door naar `/tickets?event={id}&toegevoegd=1`,
waar je de melding "Het ticket is succesvol toegevoegd." ziet én de
bijgewerkte beschikbaarheid ("X van Y tickets beschikbaar").

| Situatie | Melding |
| --- | --- |
| Geen editie gekozen | "Kies een editie." (HTTP 422) |
| Gekozen editie heeft geen tijdsloten | "Er zijn momenteel geen tijdsloten beschikbaar voor deze editie." (HTTP 422) |
| Aantal nul of niet-numeriek | "Vul een geldig aantal in (minimaal 1)." (HTTP 422) |
| Tijdslot uitverkocht | "Dit tijdslot is uitverkocht. Kies een ander tijdslot." (HTTP 422) |
| Meer tickets dan beschikbaar | "Er zijn nog maar X tickets beschikbaar voor dit tijdslot." (HTTP 422) |
| Technische fout, bijvoorbeeld database niet bereikbaar | "Het ticket kon niet worden opgeslagen. Probeer het later opnieuw." (HTTP 503) |

Bij een fout wordt er niets opgeslagen en blijft het formulier staan. Beide
stored procedures controleren de ingevulde waarden ook nog een keer, zodat er
nooit een dubbel of ongeldig tijdslot in de tabel belandt en er nooit meer
verkocht wordt dan de capaciteit.

De scenario's van beide pagina's zijn te controleren met:

```
php database/test-ticket-toevoegen.php
```

## Tickets kopen (bezoeker)

De bestelflow uit de user story *"Als bezoeker wil ik een ticket voor een
evenement kunnen kopen"*, bereikbaar vanaf de knop **Bestellen** per tijdslot
(op `/tickets` én op de evenementpagina `/events/{id}`).

### 1. Bestellen — `/tickets/bestellen`

De knop opent het bestelformulier met `?event={id}&tijdslot={prijsId}`, met dat
tijdslot al geselecteerd. De bezoeker kiest het aantal tickets en vult naam en
e-mailadres in (nodig voor de bevestiging).

- **Betaalstap:** na **Bestellen** volgt het gesimuleerde betaalscherm
  (`POST /tickets/bestellen`) met de knoppen **Betaling afronden** en
  **Betaling mislukt**. Er is op dat moment nog niets opgeslagen.
- **Betaling afronden** (`POST /tickets/betalen`) slaat de bestelling op via de
  stored procedure `sp_bestelling_plaatsen`: de bezoeker wordt aangemaakt (of
  hergebruikt op e-mailadres) en het ticket wordt aan die bezoeker gekoppeld
  (`ticket.bezoeker_id` is daardoor niet meer `NULL`). De procedure werkt in
  één transactie met de rij van het tijdslot vastgepakt (`FOR UPDATE`), dus er
  kan nooit meer verkocht worden dan de capaciteit.
- **Bevestiging:** de e-mail gaat naar de technische log (er is geen
  mailserver) en de bezoeker ziet op `/tickets` de melding plus de
  bevestiging met de ticketgegevens.

**Happy flow:** `POST /tickets/betalen` stuurt door (303, PRG) naar
`/tickets?event={id}&besteld=1&ticket={nr}` met de melding **"Je bestelling is
gelukt."** en daaronder "Bevestiging verstuurd naar {e-mailadres}" met editie,
dag, tijdslot, tarief, aantal en totaal.

Unhappy flows:

| Situatie | Melding |
| --- | --- |
| Naam leeg | "Vul je naam in." (HTTP 422) |
| Naam langer dan 100 tekens | "Je naam mag maximaal 100 tekens bevatten." (HTTP 422) |
| E-mailadres leeg | "Vul je e-mailadres in." (HTTP 422) |
| E-mailadres ongeldig | "Vul een geldig e-mailadres in, bijvoorbeeld jan@voorbeeld.nl." (HTTP 422) |
| Aantal nul of niet-numeriek | "Vul een geldig aantal in (minimaal 1)." (HTTP 422) |
| Tijdslot uitverkocht | "Dit tijdslot is uitverkocht. Kies een ander tijdslot." (HTTP 422) |
| Meer tickets dan beschikbaar | "Er zijn nog maar X tickets beschikbaar voor dit tijdslot." (HTTP 422) |
| **Betaling mislukt** (knop op het betaalscherm) | "De betaling is niet gelukt. Er is geen ticket aan je bestelling toegewezen." — er wordt niets geplaatst (303 → `/tickets?event={id}&betaling=mislukt`) |
| Technische fout, bijvoorbeeld database niet bereikbaar | "Je bestelling is niet geplaatst. Probeer het later opnieuw." (HTTP 503) — er komt geen ticket en geen bezoeker bij |

Bij een validatiefout blijft het formulier staan en wordt er niets
opgeslagen; de stored procedure voert dezelfde controles nog een keer uit.

### De scenario's laten zien

```
# ongeldige invoer: naam leeg laten staan (422)
# mislukte betaling: op het betaalscherm op "Betaling mislukt" klikken
# database niet bereikbaar (503):
perl -pi -e "s/3306/3307/ if /'port'/" config/config.php
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://127.0.0.1:8000/tickets/betalen \
  -d "evenement_id=2&prijs_id=6&aantal=1&naam=Test&emailadres=t@x.nl&betaling=geslaagd"
git restore config/config.php        # niet vergeten
```

De hele flow is ook geautomatiseerd te controleren (draait de server op
`127.0.0.1:8000` en ruimt zichzelf op):

```
php database/test-bestelling.php
```

## Structuur

- `app/` — MVC code (Controllers, Models, Views, Core)
- `config/` — configuratie (database credentials)
- `database/` — SQL schema, stored procedures, seed data
- `public/` — front controller, assets, router
- `storage/` — logs
