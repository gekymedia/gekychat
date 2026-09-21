# GekyChat desktop release downloads

Build on your dev machine with `gekychat_desktop/scripts/release-desktop-windows.ps1`, then deploy
(`gekychat/deploy.ps1` uploads `public/downloads`).

## Layout

| Path | Purpose |
|------|---------|
| `GekyChat-Setup-{version}-{build}.exe` | Versioned installer (kept; never overwritten across builds) |
| `GekyChat-Setup-latest.exe` | Always the newest build (landing / convenience link) |
| `archive/GekyChat-Setup-*.exe` | Previous installers moved here before a new release |

Example: `GekyChat-Setup-1.0.0-2.exe` for pubspec `1.0.0+2`.

Public URLs:

- Latest: `https://gekychat.com/downloads/GekyChat-Setup-latest.exe`
- Specific: `https://gekychat.com/downloads/GekyChat-Setup-1.0.0-2.exe`
- Archive: `https://gekychat.com/downloads/archive/<filename>`

Configure `APP_VERSION_WINDOWS_URL` (or Admin → App Versions) to the **versioned** file so updates point at a stable artifact.
