#!/usr/bin/env python3
"""Load CUG .env INBOUND_IMAP_* and test IMAP inbox listing."""
import imaplib
import ssl
from pathlib import Path

env_path = Path("/home/gekymedia/web/catholicuniversityofghana.com/public_html/.env")
vals = {}
for line in env_path.read_text(errors="ignore").splitlines():
    if not line or line.startswith("#") or "=" not in line:
        continue
    k, v = line.split("=", 1)
    vals[k.strip()] = v.strip().strip("\"'")

host = vals.get("INBOUND_IMAP_HOST")
user = vals.get("INBOUND_IMAP_USERNAME")
password = vals.get("INBOUND_IMAP_PASSWORD")
port = int(vals.get("INBOUND_IMAP_PORT", "993"))
print(f"host={host} user={user} pass_len={len(password or '')}")

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
M = imaplib.IMAP4_SSL(host if host != "mail.gekychat.com" else "127.0.0.1", port, ssl_context=ctx)
# Try configured host first via 127 if mail.gekychat.com
try:
    M.login(user, password)
except Exception as e:
    print("login via 127 failed:", e)
    M = imaplib.IMAP4_SSL(host, port, ssl_context=ctx)
    M.login(user, password)
print("LOGIN_OK")
typ, data = M.select("INBOX")
print("select", typ, "exists", data)
typ, data = M.search(None, "ALL")
uids = data[0].split() if data and data[0] else []
print("inbox_count", len(uids))
# newest 8
for uid in uids[-8:]:
    typ, msg = M.fetch(uid, "(BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE)])")
    if typ != "OK":
        continue
    hdr = b""
    for part in msg:
        if isinstance(part, tuple):
            hdr = part[1]
    print("---")
    print(hdr.decode(errors="ignore").strip())
M.logout()
