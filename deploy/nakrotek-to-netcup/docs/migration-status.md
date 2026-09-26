# Migration status 2026-09-26 (final DNS cutover)

## GekyChat — confirmed correct

GekyChat was **not** overwritten with Nakrotek data.

- App stayed at `/var/www/chat.gekychat.com` (netcup tree from ~Sep 25).
- Pre-Hestia dump was taken **from netcup**, then restored after Hestia’s MariaDB reset.
- Live metrics: 227 users, 3746 messages; latest message timestamp during this migration window.
- `chat` / `api` / `web` / `live` / `monitor` were never restored as Nakrotek Hestia sites.

## DNS — pointed to netcup

Authoritative zones on Nakrotek Hestia BIND (`ns1`/`ns2.gekymedia.com`) updated:

| Record type | Target |
|-------------|--------|
| Site / mail / webmail / cp **A** | `159.195.249.203` |
| SPF | `ip4:159.195.249.203` |
| **ns1 / ns2 A** (glue for BIND host) | still `91.98.200.25` so DNS keeps answering until BIND is moved |

All 32 DNS zones verified `@127.0.0.1` → netcup. Google Public DNS (`8.8.8.8`) already shows netcup for checked sites.

## Mail

MX still targets `mail.<domain>`; those **mail A** records now point at netcup, so new mail goes to netcup. Maildirs were synced earlier (~1.1G).

## Decommission Nakrotek

Keep Nakrotek up briefly as DNS-only (ns1/ns2). When ready:

1. Install/enable BIND (named) on netcup Hestia, copy zones, update registrar glue for ns1/ns2 → netcup.
2. Power off / cancel Nakrotek.

## Panel

- Hestia: `https://159.195.249.203:8083`
- Admin password: `/root/pre-hestia-backup/latest/hestia-admin.password`
