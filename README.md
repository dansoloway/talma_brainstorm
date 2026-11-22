# Prompt Writing App

About 30 people spend roughly 2 hours writing responses to prompts. Every keystroke is autosaved. Organizers set up stages and prompts, then read and export everything in an admin panel.

- **Participants** sign in at `/login` with a `@talmaisrael.com` Google account, choose 1 to 5 prompts on each stage, and write. Their text saves about 2 seconds after they stop typing, and also when they switch pages or close the tab. A copy is kept in the browser until the server confirms it. First sign-in creates their participant record.
- **Organizers** use `/admin` (Filament) to manage stages and prompts, watch who is writing, read responses by prompt or by person, and export CSV files. Sign-in is Google-only, limited to the emails in `ADMIN_EMAILS`.

## Run it locally

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

```bash
touch database/database.sqlite && php artisan migrate --seed
```

```bash
php artisan serve
```

The seeder creates 3 demo stages, 30 prompts in English and Hebrew, 3 participants, and organizer accounts for the emails in `ADMIN_EMAILS`. Participants sign in with Google (`@talmaisrael.com`).

Run the tests:

```bash
php artisan test
```

## Before the event (organizer checklist)

1. **Admin → Stages**: create the stages in order, drag them to reorder. Open each one to add its 10 prompts, or use **Bulk add** to paste them one per line. A badge warns when a stage doesn't have exactly 10 visible prompts.
2. Click **Preview** on each stage to see it exactly as participants will.
3. Share the app URL. Anyone with a `@talmaisrael.com` Google account can open `/login` and start writing — no personal links.
4. Once someone has written a response to a prompt, it can't be deleted, only hidden. Edits show a warning, and each response keeps a copy of the original prompt text.

## During the event

- The **Dashboard** refreshes every 15 seconds and shows how many people are writing right now, how many pressed Done, and the time of the last save.
- Change nothing: no deploys, no edits to prompts.

## After the event

- **Responses by prompt**: every prompt with how many people chose it. Click one to read all its responses.
- **Participants**: everyone with how many prompts they answered. Click one to read everything they wrote, in stage order.
- Dropped (unselected) responses are hidden by default. Use the **Selected** filter to see them.
- **Export CSV**: everything, or one prompt's or one person's responses. One row per response, with a UTF-8 marker so Excel shows Hebrew correctly.
- Take a database backup.

## Production (Cloudways)

`.env` settings that matter:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
APP_LOCALE=he            # Hebrew right-to-left UI; use en for English
DB_CONNECTION=mysql      # utf8mb4
SESSION_DRIVER=redis     # or database; never file
SESSION_LIFETIME=480     # 8 hours, so sessions never expire mid-event
CACHE_STORE=redis
LOG_LEVEL=warning
```

Deploy steps:

```bash
composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize && php artisan filament:optimize
```

Set Google OAuth credentials (Cloud Console → OAuth client, authorized redirect `${APP_URL}/auth/google/callback`):

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
GOOGLE_ALLOWED_DOMAIN=talmaisrael.com
ADMIN_EMAILS=daniel@talmaisrael.com,dvir.ido@talmaisrael.com
```

Participants sign in at `/login` with any verified Google account on `GOOGLE_ALLOWED_DOMAIN`. Organizers sign in at `/admin` with Google; only `ADMIN_EMAILS` can enter. First successful login creates the account.

Also check the PHP-FPM worker count on Cloudways and confirm Redis is enabled. Load-test on staging with k6 before the event (see `.cursor/rules/reliability-deploy.mdc`).

## How it's built

| Piece | Where |
| --- | --- |
| Business rules (5-per-stage limit, keep text on drop, no-duplicate saves) | `app/Actions/` |
| Participant pages and JSON endpoints | `app/Http/Controllers/ParticipantController.php`, `routes/web.php` |
| Autosave, localStorage backup, retries | `public/js/stage.js` (no build step) |
| Participant styles | `public/css/app.css` |
| Hebrew UI text | `lang/he.json` |
| Admin panel | `app/Filament/` |
| CSV export | `app/Http/Controllers/ExportController.php` |
| Tests | `tests/Feature/` |
| Rules for Cursor | `.cursor/rules/` |
