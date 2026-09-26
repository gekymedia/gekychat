# MX / mail cutover (Wave 2)

Maildirs are already synced to netcup (`/home/gekymedia/mail`, ~1.1G).
Mail domains and accounts exist in Hestia user `gekymedia`.

## Before flipping MX

1. Re-run a final delta sync:

   ```bash
   cd deploy/nakrotek-to-netcup/scripts
   ./06-wave2-mail.sh
   ```

2. Confirm web A records already point at `159.195.249.203`.

## DNS changes per mail domain

For each of: `gekymedia.com`, `schoolsgh.com`, `fabamall.gekymedia.com`,
`fywoodworks.gekymedia.com`, `stualumni.gekymedia.com`, `streetversity.org`,
`catholicuniversityofghana.com`, `cug.prioritysolutionsagency.com`,
`patriksolutions.com`, `barffoods.com`, `prioritysolutionsagency.com`,
`hopebridgecs.com` (and `gekychat.com` only if you want mail on netcup too):

| Record | Value |
|--------|--------|
| MX | `mail.<domain>` priority 0 (or Hestia default) |
| A for `mail.<domain>` | `159.195.249.203` |
| SPF | include netcup IP; remove `91.98.200.25` |
| DKIM | publish from `v-list-mail-domain-dkim gekymedia <domain>` |
| DMARC | keep; update rua if needed |

## After cutover

- Keep Nakrotek Exim/Dovecot read-only 7–14 days.
- Then power off / cancel Nakrotek.

**Do not change MX until web cutover is verified.**
