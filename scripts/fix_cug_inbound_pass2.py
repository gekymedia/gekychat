from pathlib import Path
import re
import subprocess
import imaplib
import ssl

APP = Path("/home/gekymedia/web/catholicuniversityofghana.com/public_html")
env_path = APP / ".env"
text = env_path.read_text(errors="ignore")

# Intended password (clean)
password = "342w8dX?q"

# Rewrite INBOUND_IMAP_PASSWORD line cleanly (no inline comment)
new_lines = []
replaced = False
for line in text.splitlines():
    if line.startswith("INBOUND_IMAP_PASSWORD="):
        new_lines.append(f'INBOUND_IMAP_PASSWORD="{password}"')
        replaced = True
    else:
        new_lines.append(line)
if not replaced:
    raise SystemExit("INBOUND_IMAP_PASSWORD line not found")
env_path.write_text("\n".join(new_lines) + "\n")
print("env updated")

r = subprocess.run(
    [
        "/usr/local/hestia/bin/v-change-mail-account-password",
        "gekymedia",
        "gekychat.com",
        "cug",
        password,
    ],
    capture_output=True,
    text=True,
)
print("hestia_change_exit=", r.returncode)

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
M = imaplib.IMAP4_SSL("127.0.0.1", 993, ssl_context=ctx)
M.login("cug@gekychat.com", password)
print("LOGIN_OK")
typ, data = M.select("INBOX")
print("INBOX", data)
typ, data = M.search(None, "ALL")
uids = data[0].split() if data and data[0] else []
print("count", len(uids))
# count from cugadmissionunit
from_admission = 0
for uid in uids:
    typ, msg = M.fetch(uid, "(BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE)])")
    hdr = ""
    for part in msg:
        if isinstance(part, tuple):
            hdr = part[1].decode(errors="ignore")
    if "cugadmissionunit" in hdr.lower() or "offer" in hdr.lower() or "admission" in hdr.lower():
        from_admission += 1
        print("---")
        print(hdr.strip())
print("admission_like_headers=", from_admission)
M.logout()

# clear config cache so Laravel picks new env
subprocess.run(["php", "artisan", "config:clear"], cwd=str(APP), capture_output=True, text=True)
print("config cleared")
