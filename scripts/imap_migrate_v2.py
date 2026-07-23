#!/usr/bin/env python3
"""One-way IMAP migrate old -> new with folder mapping for Hestia/Dovecot."""
from __future__ import annotations

import email
import json
import re
import ssl
import sys
import imaplib

OLD_HOST = "95.217.150.158"
NEW_HOST = "127.0.0.1"

FOLDER_MAP = {
    "INBOX": "INBOX",
    "INBOX.Sent": "Sent",
    "INBOX.Spam": "Spam",
    "INBOX.Trash": "Trash",
    "INBOX.Drafts": "Drafts",
    "INBOX.Junk": "Spam",
    "INBOX.Failed": "Failed",
    "INBOX.Processed": "Processed",
    "Sent": "Sent",
    "Spam": "Spam",
    "Trash": "Trash",
    "Drafts": "Drafts",
    "Failed": "Failed",
    "Processed": "Processed",
}


def connect(host: str, user: str, password: str) -> imaplib.IMAP4_SSL:
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    client = imaplib.IMAP4_SSL(host, 993, ssl_context=ctx)
    client.login(user, password)
    return client


def parse_list_name(raw: bytes) -> str:
    line = raw.decode(errors="ignore")
    m = re.search(r'"([^"]+)"\s*$', line)
    if m:
        return m.group(1)
    return line.split()[-1].strip('"')


def list_folders(client: imaplib.IMAP4_SSL) -> list[str]:
    typ, data = client.list()
    if typ != "OK" or not data:
        return []
    return [parse_list_name(x) for x in data if isinstance(x, (bytes, bytearray))]


def select_mailbox(client: imaplib.IMAP4_SSL, name: str, readonly: bool = False) -> bool:
    for candidate in (name, f'"{name}"'):
        typ, _ = client.select(candidate, readonly=readonly)
        if typ == "OK":
            return True
    return False


def ensure_mailbox(client: imaplib.IMAP4_SSL, name: str) -> bool:
    if name.upper() == "INBOX":
        return select_mailbox(client, "INBOX")
    for candidate in (name, f'"{name}"'):
        try:
            client.create(candidate)
        except Exception:
            pass
    return select_mailbox(client, name)


def collect_message_ids(client: imaplib.IMAP4_SSL) -> set[str]:
    existing: set[str] = set()
    typ, data = client.uid("search", None, "ALL")
    if typ != "OK" or not data or not data[0]:
        return existing
    uids = data[0].split()
    for i in range(0, len(uids), 100):
        batch = b",".join(uids[i : i + 100])
        typ, msg = client.uid("fetch", batch, "(BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])")
        if typ != "OK" or not msg:
            continue
        for part in msg:
            if not isinstance(part, tuple):
                continue
            hdr = part[1].decode(errors="ignore")
            for line in hdr.splitlines():
                if line.lower().startswith("message-id:"):
                    existing.add(line.split(":", 1)[1].strip().lower())
    return existing


def extract_flags_and_date(meta: str):
    flags = None
    fm = re.search(r"FLAGS \(([^)]*)\)", meta)
    if fm:
        cleaned = [f for f in fm.group(1).split() if f.upper() not in ("\\RECENT",)]
        flags = "(" + " ".join(cleaned) + ")" if cleaned else None
    date = None
    dm = re.search(r'INTERNALDATE "([^"]+)"', meta)
    if dm:
        # Python imaplib Time2Internaldate requires already-quoted string on some versions
        date = '"' + dm.group(1) + '"'
    return flags, date


def migrate_account(job: dict) -> bool:
    label = job["label"]
    print(f"\n===== {label} =====", flush=True)
    try:
        old = connect(OLD_HOST, job["old_user"], job["old_pass"])
        print("OLD login OK", flush=True)
    except Exception as exc:
        print(f"OLD login FAIL: {exc}", flush=True)
        return False
    try:
        new = connect(NEW_HOST, job["new_user"], job["new_pass"])
        print("NEW login OK", flush=True)
    except Exception as exc:
        print(f"NEW login FAIL: {exc}", flush=True)
        old.logout()
        return False

    folders = list_folders(old)
    print(f"Old folders: {folders}", flush=True)

    total_copied = 0
    total_skipped = 0

    for old_folder in folders:
        new_folder = FOLDER_MAP.get(
            old_folder,
            old_folder.split(".")[-1] if old_folder.startswith("INBOX.") else old_folder,
        )
        print(f"\n-- {old_folder} -> {new_folder}", flush=True)

        if not select_mailbox(old, old_folder, readonly=True):
            print("  cannot select on OLD", flush=True)
            continue
        if not ensure_mailbox(new, new_folder):
            print("  cannot create/select on NEW", flush=True)
            continue

        existing = collect_message_ids(new)
        typ, data = old.uid("search", None, "ALL")
        if typ != "OK" or not data or not data[0]:
            print("  empty on OLD", flush=True)
            continue
        uids = data[0].split()
        print(f"  old={len(uids)} existing_new={len(existing)}", flush=True)

        copied = skipped = 0
        for i, uid in enumerate(uids, 1):
            typ, msg = old.uid("fetch", uid, "(RFC822 FLAGS INTERNALDATE)")
            if typ != "OK" or not msg:
                continue
            raw = None
            meta = ""
            for part in msg:
                if isinstance(part, tuple) and len(part) >= 2:
                    meta = (
                        part[0].decode(errors="ignore")
                        if isinstance(part[0], (bytes, bytearray))
                        else str(part[0])
                    )
                    if isinstance(part[1], (bytes, bytearray)):
                        raw = part[1]
            if raw is None:
                continue

            try:
                em = email.message_from_bytes(raw)
                mid = (em.get("Message-ID") or "").strip().lower()
            except Exception:
                mid = ""

            if mid and mid in existing:
                skipped += 1
                continue

            flags, date = extract_flags_and_date(meta)
            uid_s = uid.decode() if isinstance(uid, (bytes, bytearray)) else str(uid)
            try:
                new.append(new_folder, flags, date, raw)
                copied += 1
                if mid:
                    existing.add(mid)
            except Exception:
                try:
                    # fallback: no date
                    new.append(new_folder, flags, None, raw)
                    copied += 1
                    if mid:
                        existing.add(mid)
                except Exception as exc2:
                    print(f"  append fail uid={uid_s}: {exc2}", flush=True)

            if i % 25 == 0:
                print(f"  progress {i}/{len(uids)} copied={copied} skipped={skipped}", flush=True)

        print(f"  done copied={copied} skipped={skipped}", flush=True)
        total_copied += copied
        total_skipped += skipped

    try:
        old.logout()
    except Exception:
        pass
    try:
        new.logout()
    except Exception:
        pass

    print(f"TOTAL {label}: copied={total_copied} skipped={total_skipped}", flush=True)
    return True


def main() -> int:
    jobs = json.load(open(sys.argv[1], encoding="utf-8"))
    ok = True
    for job in jobs:
        if not migrate_account(job):
            ok = False
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
