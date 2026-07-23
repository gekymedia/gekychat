from pathlib import Path
import re
p = Path("/usr/local/sbin/imap_migrate.py")
t = p.read_text()
t2, n = re.subn(
    r"def connect\(host.*?\n    return M\n",
    """def connect(host, user, password, port=993):
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    M = imaplib.IMAP4_SSL(host, port, ssl_context=ctx)
    M.login(user, password)
    return M
""",
    t,
    count=1,
    flags=re.S,
)
if n != 1:
    raise SystemExit(f"replace count={n}")
p.write_text(t2)
print("patched ok")
