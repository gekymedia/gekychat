# Server-side: push + queues (GekyChat)

See the mobile playbook for the **full product picture**:  
`gekychat_mobile/docs/RELIABILITY_WHATSAPP_PARITY.md`

## Quick checklist (Laravel)

1. **FCM** — high priority for chat/call payloads; valid device tokens; remove stale tokens.  
2. **Queues** — prefer **Redis** + **multiple workers** under load; keep `failed_jobs` monitored.  
3. **Reverb** — required for **foreground** realtime; **FCM** for **background**.  
4. **After deploy** — `php artisan queue:restart` (already in `deploy.ps1`).  

## Supervisor

Production uses `queue-worker-chat-gekychat-com` (see server `supervisorctl status`).  
Repo template: `deploy/supervisor/gekychat-worker.conf` — align `numprocs` with production if you scale workers.
