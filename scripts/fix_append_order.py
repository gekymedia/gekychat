from pathlib import Path
import re

p = Path("/usr/local/sbin/imap_migrate.py")
t = p.read_text()

# Fix append call order: append(mailbox, flags, date_time, message)
old_block = '''            try:
                if flags and date:
                    new.append(folder if " " not in folder else f'"{folder}"', raw, flags, date)
                elif date:
                    new.append(folder if " " not in folder else f'"{folder}"', raw, None, date)
                else:
                    new.append(folder if " " not in folder else f'"{folder}"', raw)
                copied += 1
                if mid:
                    existing.add(mid)
            except Exception as e:
                # retry with quoted mailbox
                try:
                    box = f'"{folder}"'
                    new.append(box, raw, flags, date) if date else new.append(box, raw)
                    copied += 1
                    if mid:
                        existing.add(mid)
                except Exception as e2:
                    print(f"  append fail uid={uid.decode()}: {e2}", flush=True)'''

new_block = '''            box = f'"{folder}"' if ((" " in folder) or ("/" in folder) or ("." in folder and folder.upper() != "INBOX")) else folder
            # Prefer unquoted for INBOX.*; dovecot accepts both
            if folder.upper() == "INBOX":
                box = "INBOX"
            else:
                box = folder
            try:
                # imaplib signature: append(mailbox, flags, date_time, message)
                fl = f"({flags})" if flags else None
                new.append(box, fl, date, raw)
                copied += 1
                if mid:
                    existing.add(mid)
            except Exception as e2:
                try:
                    new.append(f'"{box}"', fl, date, raw)
                    copied += 1
                    if mid:
                        existing.add(mid)
                except Exception as e3:
                    print(f"  append fail uid={uid.decode()}: {e3}", flush=True)'''

if old_block not in t:
    raise SystemExit("block not found")
p.write_text(t.replace(old_block, new_block))
print("fixed append order")
