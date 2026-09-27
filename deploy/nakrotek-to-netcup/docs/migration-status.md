# Migration status 2026-09-26 (visual content verification)

## GekyChat — confirmed correct

GekyChat was **not** overwritten with Nakrotek data.

- App stayed at `/var/www/chat.gekychat.com` (netcup tree from ~Sep 25).
- Pre-Hestia dump was taken **from netcup**, then restored after Hestia’s MariaDB reset.
- Live metrics: 227 users, 3746 messages; latest message timestamp during this migration window.
- `chat` / `api` / `web` / `live` / `monitor` were never restored as Nakrotek Hestia sites.
- Note: `https://chat.gekychat.com/` root still returns the app 404 page (API/services healthy); same behaviour as pre-migration SPA routing.

## DNS — pointed to netcup

Authoritative zones on Nakrotek Hestia BIND (`ns1`/`ns2.gekymedia.com`) updated:

| Record type | Target |
|-------------|--------|
| Site / mail / webmail / cp **A** | `159.195.249.203` |
| SPF | `ip4:159.195.249.203` |
| **ns1 / ns2 A** (glue for BIND host) | still `91.98.200.25` so DNS keeps answering until BIND is moved |

A-record TTLs on key zones lowered to **300s** and SOA serials bumped (2026-09-26) so resolvers drop stale Nakrotek answers faster. Authoritative `@127.0.0.1` → netcup for all checked zones.

## Visual content audit (forced to netcup IP)

HTTP 200 alone is not enough — pages were loaded with Host/`--resolve` / browser host-resolver-rules → `159.195.249.203`, then screenshotted.

### Fixed so real apps render on netcup

| Issue | Fix |
|-------|-----|
| CUG / Laravel “Coming Soon” or wrong tree | `cug.prioritysolutionsagency.com` custom docroot → `catholicuniversityofghana.com/public_html/public` (matches Nakrotek) |
| Deep links 404 (Hestia default nginx) | Patched `default.tpl`/`default.stpl` with `try_files … /index.php` |
| MySQL “Access denied” | Recreated grants from each site `.env` (shared users granted on all their DBs) |
| gekymedia `.env` `DB_DATABASE` polluted with comment | Cleaned to `gekymedia_gekymedia` |
| hopebridge / streamvault sqlite | Installed `php8.5-sqlite3`; ran streamvault migrations |

### Verified live content (screenshots under `/opt/cursor/artifacts/screenshots/`)

| Site | Result |
|------|--------|
| catholicuniversityofghana.com / cug.prioritysolutionsagency.com | Real CUG Priority Admissions portal |
| gekymedia.com | Real GEKYMEDIA site |
| schoolsgh.com | Real SchoolsGH marketing site |
| fabamall.gekymedia.com | Real Fabamall |
| hopebridgecs.com | Real HopeBridge |
| prioritysolutionsagency.com | Real PSA |
| patriksolutions.com | Real Patrik Solutions |
| barffoods.com | Real BarfFoods |
| blacktask / churchgh / fywoodworks / scp / agribusiness / admin.schoolsgh logins | Real app login UIs |
| angutech / accommodations / streetversity / stualumni / streamvault / bank / ai.patrik | Real apps |

### Still placeholder / expected empty (same on Nakrotek source)

- School tenant shells with only Hestia Coming Soon `index.html` (~12K): `bkec`, `brillianttest`, `st-marys`, `st-theresas`, `studyland`, `testing.schoolsgh.com`, `audit.patriksolutions.com`, `cp.gekymedia.com`, etc.
- `chat.gekychat.com/` → app 404 page (not Coming Soon).

## Mail

MX still targets `mail.<domain>`; those **mail A** records point at netcup. Maildirs were synced earlier (~1.1G).

## Decommission Nakrotek

Keep Nakrotek up briefly as DNS-only (ns1/ns2). When ready:

1. Install/enable BIND (named) on netcup Hestia, copy zones, update registrar glue for ns1/ns2 → netcup.
2. Power off / cancel Nakrotek.

## Panel

- Hestia: `https://159.195.249.203:8083`
- Admin password: `/root/pre-hestia-backup/latest/hestia-admin.password`
