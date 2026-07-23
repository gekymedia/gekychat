import json
import ssl
import imaplib

jobs = json.load(open("/root/mail-recovery/migrate_jobs.json"))


def probe(host, user, password):
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    M = imaplib.IMAP4_SSL(host, 993, ssl_context=ctx)
    M.login(user, password)
    typ, _ = M.select("INBOX", readonly=True)
    n = 0
    if typ == "OK":
        typ, data = M.search(None, "ALL")
        if typ == "OK" and data and data[0]:
            n = len(data[0].split())
    typ, data = M.list()
    folders = []
    for raw in data or []:
        line = raw.decode(errors="ignore")
        folders.append(line.split()[-1].strip('"'))
    M.logout()
    return n, folders


for j in jobs:
    print(j["label"])
    try:
        n, f = probe("95.217.150.158", j["old_user"], j["old_pass"])
        print(" OLD OK inbox=", n, " folders=", f)
    except Exception as e:
        print(" OLD FAIL", e)
    try:
        n, f = probe("127.0.0.1", j["new_user"], j["new_pass"])
        print(" NEW OK inbox=", n)
    except Exception as e:
        print(" NEW FAIL", e)
