# Migration status 2026-09-26T05:30:44Z

## Completed on netcup
- Hestia 1.10.5 installed (panel https://cp.gekychat.com:8083 / https://159.195.249.203:8083)
- GekyChat restored (DB + workers/reverb + LiveKit); API health OK
- Websites + DBs migrated for batches A–D (Host-header smoke 200)
- Mail domains created; maildirs synced (~1.1G). **MX still on Nakrotek until you flip DNS.**

## DNS A/AAAA cutover (manual — leave MX on Nakrotek for Wave 2 soak)
Point these A records to `159.195.249.203` (TTL 300). Do **not** change MX yet.

- `gekymedia.com` A=[91.98.200.25 ] MX=[0 mail.gekymedia.com. ] → **NEED_CUTOVER**
- `cug.prioritysolutionsagency.com` A=[91.98.200.25 ] MX=[0 mail.cug.prioritysolutionsagency.com. ] → **NEED_CUTOVER**
- `prioritysolutionsagency.com` A=[91.98.200.25 ] MX=[0 mail.prioritysolutionsagency.com. ] → **NEED_CUTOVER**
- `catholicuniversityofghana.com` A=[91.98.200.25 ] MX=[0 mail.catholicuniversityofghana.com. ] → **NEED_CUTOVER**
- `schoolsgh.com` A=[91.98.200.25 ] MX=[0 mail.schoolsgh.com. ] → **NEED_CUTOVER**
- `fabamall.gekymedia.com` A=[91.98.200.25 ] MX=[0 mail.fabamall.gekymedia.com. ] → **NEED_CUTOVER**
- `patriksolutions.com` A=[91.98.200.25 ] MX=[0 mail.patriksolutions.com. ] → **NEED_CUTOVER**
- `barffoods.com` A=[91.98.200.25 ] MX=[0 mail.barffoods.com. ] → **NEED_CUTOVER**
- `streetversity.org` A=[] MX=[] → **NEED_CUTOVER**
- `hopebridgecs.com` A=[91.98.200.25 ] MX=[0 mail.hopebridgecs.com. ] → **NEED_CUTOVER**
- `chat.gekychat.com` A=[159.195.249.203 ] MX=[0 mail.chat.gekychat.com. ] → **ALREADY_NETCUP**

## MX cutover (after web stable 1–7 days)
1. Final maildir rsync: `./06-wave2-mail.sh`
2. Set MX for each mail domain to netcup mail host / A=`159.195.249.203`
3. Update SPF/DKIM (v-list-mail-domain-dkim gekymedia <domain>)
4. Keep Nakrotek mail read-only 7–14 days, then power off

## Panel / paths
| Item | Value |
|------|-------|
| Hestia | https://159.195.249.203:8083 (admin password in /root/pre-hestia-backup/latest/hestia-admin.password) |
| GekyChat app | /var/www/chat.gekychat.com |
| Sites user | gekymedia |
| LiveKit | /opt/livekit |
