# Nakrotek decommission checklist

Generated: 2026-09-26T05:30:50Z
Target IP: 159.195.249.203

## DNS A/AAAA (should point to netcup)

### Batch A
- `gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.gekymedia.com. ] → **FAIL**
- `cug.prioritysolutionsagency.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.cug.prioritysolutionsagency.com. ] → **FAIL**
- `prioritysolutionsagency.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.prioritysolutionsagency.com. ] → **FAIL**
- `accommodations.prioritysolutionsagency.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.accommodations.prioritysolutionsagency.com. ] → **FAIL**
- `agribusiness.prioritysolutionsagency.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.agribusiness.prioritysolutionsagency.com. ] → **FAIL**
- `angutech.prioritysolutionsagency.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**
- `bank.prioritysolutionsagency.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.bank.prioritysolutionsagency.com. ] → **FAIL**

### Batch B
- `catholicuniversityofghana.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.catholicuniversityofghana.com. ] → **FAIL**
- `schoolsgh.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.schoolsgh.com. ] → **FAIL**
- `admin.schoolsgh.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.admin.schoolsgh.com. ] → **FAIL**
- `bkec.schoolsgh.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.bkec.schoolsgh.com. ] → **FAIL**
- `brillianttest.schoolsgh.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.brillianttest.schoolsgh.com. ] → **FAIL**
- `st-marys.schoolsgh.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.st-marys.schoolsgh.com. ] → **FAIL**
- `st-theresas.schoolsgh.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**
- `studyland.schoolsgh.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**
- `testing.schoolsgh.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**

### Batch C
- `patriksolutions.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.patriksolutions.com. ] → **FAIL**
- `ai.patriksolutions.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.ai.patriksolutions.com. ] → **FAIL**
- `audit.patriksolutions.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**
- `blacktask.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.blacktask.gekymedia.com. ] → **FAIL**
- `barffoods.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.barffoods.com. ] → **FAIL**
- `hopebridgecs.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.hopebridgecs.com. ] → **FAIL**
- `hopespringfoundation.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**
- `fabamall.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.fabamall.gekymedia.com. ] → **FAIL**
- `fywoodworks.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.fywoodworks.gekymedia.com. ] → **FAIL**
- `agyei-hardware.gekymedia.com` A=[] AAAA=[] MX=[] → **FAIL**
- `churchgh.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.churchgh.gekymedia.com. ] → **FAIL**
- `ikagyei.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.ikagyei.gekymedia.com. ] → **FAIL**
- `scp.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.scp.gekymedia.com. ] → **FAIL**
- `streamvault.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**
- `streetversity.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.streetversity.gekymedia.com. ] → **FAIL**
- `streetversity.org` A=[] AAAA=[] MX=[] → **FAIL**
- `stualumni.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[0 mail.stualumni.gekymedia.com. ] → **FAIL**
- `stuirmts.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**
- `stukeylog.gekymedia.com` A=[] AAAA=[] MX=[] → **FAIL**
- `stuptm.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**

### Batch D
- `cp.gekymedia.com` A=[91.98.200.25 ] AAAA=[] MX=[] → **FAIL**

## GekyChat (must stay healthy)
- `chat.gekychat.com` A=[159.195.249.203 ]
- `api.gekychat.com` A=[159.195.249.203 ]
- `live.gekychat.com` A=[159.195.249.203 ]
- `monitor.gekychat.com` A=[159.195.249.203 ]

## Panel / deploy paths (post-Hestia)

| Item | Value |
|------|-------|
| Hestia panel | `https://cp.gekychat.com:8083` (or netcup IP:8083) |
| GekyChat app | `/var/www/chat.gekychat.com` (unchanged) |
| Hestia user (sites) | `gekymedia` |
| Hestia user (chat) | `gekychat` |
| LiveKit | `/op- `monitor.gekychat.com` A=[159.195.249.203 ]

## Nakrotek retire steps

1. Confirm all A/AAAA above are OK and HTTPS works.
2. Confirm MX on netcup for 7–14 days with no delivery gaps.
3. Set Nakrotek read-only (stop nginx/exim or firewall inbound 80/443/25).
4. Final offsite backup of Nakrotek `/home` + MySQL if desired.
5. Cancel Nakrotek hosting / power off.
6. Update deploy docs: `deploy.ps1` / `deploy.sh` paths remain `/var/www/chat.gekychat.com`.

ks.
2. Confirm MX on netcup for 7–14 days with no delivery gaps.
3. Set Nakrotek read-only (stop nginx/exim or firewall inbound 80/443/25).
4. Final offsite backup of Nakrotek `/home` + MySQL if desired.
5. Cancel Nakrotek hosting / power off.
6. Update deploy docs: `deploy.ps1` / `deploy.sh` paths remain `/var/www/chat.gekychat.com`.

[2026-09-26T05:31:03Z] Report written. Fix any FAIL DNS rows before powering off Nakrotek.
