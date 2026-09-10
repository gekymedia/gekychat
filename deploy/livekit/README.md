# LiveKit on netcup — full A/V stack

## Services (`/opt/livekit`)

| Service | Role | Ports |
|---------|------|-------|
| **livekit** | SFU + embedded TURN | 7880, 7881, 3478, 5349, UDP 50000–60000 |
| **livekit-egress** | Record + RTMP out | (host net, Chrome) |
| **livekit-ingress** | RTMP/WHIP in (OBS) | 1935, 8085, UDP 7885 |
| **prometheus** | Metrics scrape | 127.0.0.1:9090 |
| **grafana** | Dashboards | https://monitor.gekychat.com |

Recordings directory: `/var/www/chat.gekychat.com/storage/app/livekit-recordings`

Grafana admin password: `/opt/livekit/grafana.admin.password`

## Laravel API

- `POST /api/v1/live/{id}/egress/record` — start MP4 recording
- `POST /api/v1/live/{id}/egress/rtmp` — `{ "rtmp_url": "rtmp://..." }`
- `POST /api/v1/live/{id}/egress/stop`
- `POST /api/v1/live/{id}/ingress` — OBS RTMP (+ WHIP when available)

Auto-record when `save_replay=true` and phase recording is enabled.

## Client quality

Mobile + desktop use `LiveKitQuality`:
- Calls: 720p @ 2.5 Mbps, simulcast, dynacast
- Broadcast host: 1080p @ 4.5 Mbps
- Screen share: 1080p30 @ 4 Mbps
- Viewers: adaptiveStream on

## Ops

```bash
cd /opt/livekit
docker compose ps
docker compose logs -f --tail=100 livekit egress ingress
docker compose pull && docker compose up -d
```
