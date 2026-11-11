# Render: seeding the database with the legacy data

> **Since 2026-11-11 local development and Render share one test database on Neon (Singapore).** `DB_URL` in
> `sfk-platform/.env` and in Render's Environment point at the same database (local: the direct string, Render: direct or pooled — named prepared statements are off, see `config/database.php`) (`render.yaml`: `sync: false`;
> Render's own database is no longer used). The local data was copied there with `pg_dump` / `pg_restore` (every table's
> row count checked). Consequences: what you change locally is live on Render and the other way round; `migrate:fresh`,
> `db:wipe` or a legacy import run locally hit the shared data; a deploy migrates it (`docker/render/start.sh`). Tests
> are not affected — `phpunit.xml` / `phpunit.pgsql.xml` force `DB_URL` empty (SQLite / the local `sfk_test`). Keep
> `SUPER_ADMIN_*` the same on both sides (`core:super-admin` demotes the other env-managed account), and uploaded files
> stay on the machine that received them (`UPLOADS_DISK`). The steps below are for a fresh import into a database.

How the legacy system's data (`data_json/`, see [migration.md](migration.md)) gets into the PostgreSQL database of the
Render deployment (`render.yaml` at the repository root, image `docker/render/`).

## Why not import on Render directly

- `data_json/` (624 MB, business and personal data) is git-ignored and kept out of the image (`.dockerignore`): Render
  never has the files, and free services have no shell to run `legacy:import`.
- Running `legacy:import` on your PC straight against Render works, but every one of the many small queries crosses the
  network to Singapore — hours instead of minutes.

So the data is imported into a **separate local database** and copied to Render **in one go** (`pg_dump` →
`pg_restore`). This works because:

- the imported data is about 200 MB — within the free plan's 1 GB;
- the importers write only to the database (no files, nothing for the uploads disk);
- no column is encrypted with `APP_KEY` and passwords are bcrypt hashes, so the copy works with Render's other key.

## Before you start

- The local Docker setup runs (`docker compose up -d`) and `data_json/` is in place.
- **The copy replaces everything in the Render database.** What was set up there (spaces, permissions, users) is lost and
  is done again afterwards (step 8). The super admin comes back by itself from the service's `SUPER_ADMIN_*` values.
- Every command below names the separate database `sfk_render_seed`: the dev database (`sfk_platform`) is never touched.
- The database user is `sfk` (`DB_USERNAME` of `.env`); use yours if it differs.

Run the commands in the `sfk-platform` folder. They are the same in PowerShell and Git Bash.

## 1. Create a separate local database

```
docker compose exec pgsql createdb -U sfk sfk_render_seed
```

## 2. Make sure the commands go to it

```
docker compose exec -e DB_DATABASE=sfk_render_seed app php artisan db:show
```

The output must name `sfk_render_seed`. If it names `sfk_platform`, stop: the configuration is cached
(`php artisan config:clear` in the container) or `.env` sets `DB_URL`.

## 3. Create the tables and import

```
docker compose exec -e DB_DATABASE=sfk_render_seed app php artisan migrate --force
docker compose exec -e DB_DATABASE=sfk_render_seed -e QUEUE_CONNECTION=sync -e MAIL_MAILER=log app php artisan legacy:import
```

- `QUEUE_CONNECTION=sync` and `MAIL_MAILER=log` keep what the import sets off (alerts, e-mails) inside this run: the
  local queue worker works on the dev database.
- The import prints every app with 作成 / 更新 / スキップ; it is safe to run again after an interruption. Warnings:
  `storage/logs/legacy-import/<app id>.log`.

## 4. Write the database to a file

```
docker compose exec pgsql pg_dump -U sfk -d sfk_render_seed -Fc --no-owner --no-privileges -f /tmp/sfk-seed.dump
```

The file stays inside the database container. Do not redirect a dump with `>` in PowerShell: it changes the bytes of a
binary file.

## 5. Pause the Render service

Render → **sfk-platform** → **Settings** → **Suspend Web Service**: its queue worker and scheduler stop writing while
the data is replaced.

## 6. Restore into Render's database

Render → **sfk-db** → **Connect** → copy the **External Database URL** and add `?sslmode=require`:

```
docker compose exec pgsql pg_restore --clean --if-exists --no-owner --no-privileges --dbname "postgresql://USER:PASSWORD@HOST/sfk_platform?sslmode=require" /tmp/sfk-seed.dump
```

- Connection refused: **sfk-db** → **Access Control** must allow your IP address.
- A few warnings about the `pg_trgm` extension or about ownership can be ignored; errors about tables cannot.

## 7. Resume the service

**Resume Web Service**. Its start (`docker/render/start.sh`) migrates (`Nothing to migrate`), restores the super admin
(`Super admin [...] is in sync`) and registers the apps (`core:apps-sync`). Follow it under **Logs**.

## 8. Check and set up

- Sign in as the super admin and open a few apps (01. 取引先マスタ, 04. 受注入力, 08. 日報入力).
- The registry starts every app as 開発中: set up スペース and アプリの権限 and publish the apps.
- Imported employees have random temporary passwords: issue passwords in ユーザー管理 to whoever should sign in.

## 9. Clean up locally

```
docker compose exec pgsql rm /tmp/sfk-seed.dump
docker compose exec pgsql dropdb -U sfk sfk_render_seed
```

## Doing it again

To take over a newer legacy export later, repeat steps 1–9 with the new `data_json/`. Everything entered on Render since
the last copy is replaced: copy only before people start working there.

## Keep in mind

The data includes personal data (employees, 作業単価) and business data. Render's free database has no backups and
expires after its trial period: use it for a demo / staging copy, and keep access to the people who need it. Production
runs on Laravel Cloud (README → Production checklist).
