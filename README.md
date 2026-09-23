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

## Structuur

- `app/` — MVC code (Controllers, Models, Views, Core)
- `config/` — configuratie (database credentials)
- `database/` — SQL schema, stored procedures, seed data
- `public/` — front controller, assets, router
- `storage/` — logs
