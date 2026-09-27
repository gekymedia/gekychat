# DNS cutover (Wave 1)

After each batch migrates and Host-header smoke tests look good:

1. Lower TTL to 300s on A/AAAA (do this 24h ahead when possible).
2. On a workstation, add temporary hosts overrides:

   ```
   159.195.249.203  gekymedia.com www.gekymedia.com
   159.195.249.203  cug.prioritysolutionsagency.com
   ```

3. Verify HTTPS (expect cert warnings until LE re-issue or cert copy).
4. Flip **A/AAAA only** to `159.195.249.203`.
5. Leave **MX** on Nakrotek until Wave 2.
6. After DNS propagates, issue Let’s Encrypt in Hestia:

   ```bash
   v-add-letsencrypt-domain gekymedia <domain> [aliases]
   ```

7. Confirm GekyChat (`chat.gekychat.com`) still healthy after the batch.
