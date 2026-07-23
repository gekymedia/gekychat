from pathlib import Path
import subprocess

APP = "/home/gekymedia/web/catholicuniversityofghana.com/public_html"
vals = {}
for line in Path(f"{APP}/.env").read_text(errors="ignore").splitlines():
    if line.startswith("INBOUND_IMAP_") and "=" in line:
        k, v = line.split("=", 1)
        vals[k] = v

raw = vals.get("INBOUND_IMAP_PASSWORD", "")
stripped = raw.strip().strip("\"'")
print("raw_repr=", repr(raw))
print("stripped_len=", len(stripped))
print("stripped_repr=", repr(stripped))
print("user=", vals.get("INBOUND_IMAP_USERNAME"))
print("host=", vals.get("INBOUND_IMAP_HOST"))

# Reset mailbox password to match .env stripped value
r = subprocess.run(
    [
        "/usr/local/hestia/bin/v-change-mail-account-password",
        "gekymedia",
        "gekychat.com",
        "cug",
        stripped,
    ],
    capture_output=True,
    text=True,
)
print("change_exit=", r.returncode)
print("change_out=", (r.stdout or "").strip()[:200])
print("change_err=", (r.stderr or "").strip()[:200])

# Test IMAP
import imaplib, ssl
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
M = imaplib.IMAP4_SSL("127.0.0.1", 993, ssl_context=ctx)
M.login("cug@gekychat.com", stripped)
print("LOGIN_OK")
typ, data = M.select("INBOX")
print("INBOX", typ, data)
typ, data = M.search(None, "ALL")
uids = data[0].split() if data and data[0] else []
print("count", len(uids))
for uid in uids[-5:]:
    typ, msg = M.fetch(uid, "(BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE)])")
    for part in msg:
        if isinstance(part, tuple):
            print("---")
            print(part[1].decode(errors="ignore").strip())
M.logout()
