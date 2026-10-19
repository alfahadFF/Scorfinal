# ALFAMAN × Supabase — connection guide

## ✅ CONNECTED & VERIFIED END-TO-END (2026-10-17)
- Database password received; `config.php` (production, pooler) is in
  place and `api.php` loads it automatically.
- Live test against this Supabase project passed completely:
  Excel import (no login — secret-URL access) (**7 categories,
  33 products, 142 spec groups, 745 spec rows, 49 media**) →
  publish_all = 33 → fresh EN + AR product pages generated with the
  scor-slider gallery and Arabic tabs.
- First test's data was truncated back to empty; afterwards the two
  Google Sheets (Skid + Pump) were imported for real: **16 products**
  (6 skid-steer incl. SY 145, 10 PM trailer pumps replacing the old
  SY- pump pages), with bilingual spec values (translations table,
  `spec_row` name/value, lang `ar`) — see `sheets-data.json` and
  `sheets-source/`. Other catalog products still live only in the
  static site / `scor-products.xlsx` until imported through the panel.
- REST state: catalog tables publicly readable (publishable key),
  `users` blocked (42501) as intended.

## What was verified (2026-10-17)
- Project `gvbogfyfmkohepmukqmw` is live: `https://gvbogfyfmkohepmukqmw.supabase.co/rest/v1/` responds.
- The `anon` JWT that was shared is **rejected on every endpoint**
  (`rest/v1`, `auth/v1/settings`, `auth/v1/health` → `Invalid API key`).
- Reason: the project's JWKS publishes a new **ES256 (P-256) signing key**,
  while the shared key is a legacy **HS256** JWT signed with the old shared
  secret. Projects migrated to the new API-key system refuse legacy anon
  JWTs. The key's payload (ref, role, expiry 2036) is well-formed — it is
  simply no longer accepted by this project.

## Status update — publishable key received & project inspected
- The new **publishable key works** (`sb_publishable_…`, Auth settings respond).
- The project already contains **9 of the 10 ALFAMAN tables** — all empty:
  users, categories, products, spec_groups, spec_rows, media,
  product_files, translations, activity_log.
- `product_extras` (added to the schema later) is **missing** → run
  `supabase-add-extras.sql` once in the SQL Editor.
- Row Level Security is on: public **SELECT works**, **INSERT is refused**
  (42501). Writes therefore still need either the direct PostgreSQL
  connection (below) or a secret key kept server-side — never the
  publishable key.
- ⚠️ `users` and `activity_log` are also publicly readable via REST
  (password hashes would leak once an admin exists). The add-on SQL
  above revokes that; `api.php` connects as DB owner and is unaffected.

## Connection endpoint — pooler, not Direct (verified 2026-10-17)
- The string the dashboard shows for **Direct**
  (`db.gvbogfyfmkohepmukqmw.supabase.co:5432`) contains the literal
  placeholder `[YOUR-PASSWORD]`, and the host resolves to **IPv6 only**
  (2600:1f14:90b:6000:…, AWS us-west-2) — unreachable from this sandbox
  and from many shared hostings.
- Use **Session pooler** instead: `aws-1-us-west-2.pooler.supabase.com:5432`,
  user `postgres.gvbogfyfmkohepmukqmw`, database `postgres` (IPv4, TCP
  verified reachable). A ready file `config.supabase.php` (rename to
  `config.php`, insert password) is prepared for exactly this.
- Only missing piece: the **database password** itself (set at project
  creation; resettable under Settings → Database).

## The right way to connect ALFAMAN (recommended)
ALFAMAN's `api.php` already speaks native PostgreSQL (PDO `pgsql`).
Supabase **is** PostgreSQL — no REST layer, no anon/secret keys needed.

1. **Create the tables** — Supabase Dashboard → **SQL Editor** → paste the
   full contents of `install-pgsql.sql` → Run. (10 tables, idempotent,
   FK `ON DELETE CASCADE`.) Alternatively run it with `psql` against the
   database connection string.
2. **Get the connection values** — Dashboard top bar → **Connect** →
   *Session pooler* string:
   `postgresql://postgres.gvbogfyfmkohepmukqmw:<DB_PASSWORD>@aws-1-<region>.pooler.supabase.com:5432/postgres`
3. **Configure ALFAMAN** — copy `config.supabase.example.php` to
   `config.php` next to `api.php` and fill `host / user / pass`
   (database name is always `postgres`).
   - `pass` = the **database password** set at project creation
     (reset: Settings → Database → *Reset database password*).
   - It is **not** the anon/publishable/secret API key — those cannot
     open a PostgreSQL connection.
4. Upload `alfaman.html`, `api.php`, `config.php`, `i18n.php`, `templates/`
   next to the `scor1/` site folder and open `alfaman.html` — it works
   immediately (no accounts; the secret URL + embedded key protect it).
   Import `scor-products.xlsx`, publish.

## What each credential is for
| Credential | Where | Safe to expose? | Used by ALFAMAN? |
|---|---|---|---|
| Project URL | Dashboard → Settings → API | Yes (public) | Only for REST — not needed |
| Publishable key `sb_publishable_…` | Settings → API Keys | Yes (public, RLS-bound) | Not needed for the panel |
| Legacy `anon` JWT (the one shared) | Settings → API Keys → Legacy | Public by design, but **rejected by this project now** | No |
| Secret key `sb_secret_…` / `service_role` | Settings → API Keys | **Never** — full DB bypass | Only if a REST integration is ever built |
| **Database password** | Set at creation / Settings → Database | **Never** — goes only in `config.php` on the server | **Yes — this is the one ALFAMAN needs** |

## If REST access is wanted later
Fetch the **new** keys from Settings → API Keys (`sb_publishable_…` for
public reads with Row Level Security policies; `sb_secret_…` only
server-side). The legacy HS256 `anon` key can be re-enabled under
*Legacy API Keys*, but there is no need: the direct PostgreSQL path
above is faster, keeps the panel's no-login secret-URL model, and keeps
every secret off the browser.
