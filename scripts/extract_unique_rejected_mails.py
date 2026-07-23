#!/usr/bin/env python3
"""Extract estimated unique rejected mails to cug@gekychat.com from Exim logs."""
import csv
import glob
import gzip
import re
from collections import defaultdict
from datetime import datetime, timedelta

SENDERS = [
    "cugadmissionunit@gmail.com",
    "samuel.yaw-opoku@cug.edu.gh",
    "gekymedia@gmail.com",
]
OUT_DIR = "/root/mail-recovery"
PAT = re.compile(
    r"^(?P<ts>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}).*"
    r"H=(?P<host>\S+)\s+\[(?P<ip>[^\]]+)\].*"
    r"F=<(?P<from>[^>]+)> rejected RCPT <cug@gekychat.com>:\s*(?P<reason>.*)$"
)


def main() -> None:
    rows = []
    for path in sorted(glob.glob("/var/log/exim4/mainlog*")):
        opener = gzip.open if path.endswith(".gz") else open
        with opener(path, "rt", errors="ignore") as fh:
            for line in fh:
                if "rejected RCPT <cug@gekychat.com>" not in line:
                    continue
                m = PAT.search(line)
                if not m or m.group("from") not in SENDERS:
                    continue
                rows.append(
                    {
                        "ts": datetime.strptime(m.group("ts"), "%Y-%m-%d %H:%M:%S"),
                        "from": m.group("from"),
                        "host": m.group("host"),
                        "ip": m.group("ip"),
                        "reason": m.group("reason").strip(),
                        "raw": line.rstrip(),
                    }
                )
    rows.sort(key=lambda r: (r["from"], r["ts"]))

    clusters = []
    for sender in SENDERS:
        current = None
        for row in [r for r in rows if r["from"] == sender]:
            if current is None or (row["ts"] - current["last"]) > timedelta(minutes=30):
                current = {
                    "from": sender,
                    "first": row["ts"],
                    "last": row["ts"],
                    "n": 1,
                    "host": row["host"],
                    "ip": row["ip"],
                    "reason": row["reason"],
                    "log": row["raw"],
                }
                clusters.append(current)
            else:
                current["last"] = row["ts"]
                current["n"] += 1

    by_sender: dict[str, int] = defaultdict(int)
    csv_path = f"{OUT_DIR}/cug_unique_rejected_mails.csv"
    with open(csv_path, "w", newline="", encoding="utf-8") as fh:
        writer = csv.writer(fh)
        writer.writerow(
            [
                "sender",
                "unique_mail_no",
                "first_attempt_utc",
                "last_retry_utc",
                "smtp_attempts",
                "host",
                "ip",
                "reject_reason",
                "first_log_line",
            ]
        )
        for cluster in clusters:
            by_sender[cluster["from"]] += 1
            writer.writerow(
                [
                    cluster["from"],
                    by_sender[cluster["from"]],
                    cluster["first"],
                    cluster["last"],
                    cluster["n"],
                    cluster["host"],
                    cluster["ip"],
                    cluster["reason"],
                    cluster["log"],
                ]
            )

    summary_path = f"{OUT_DIR}/cug_unique_rejected_summary.txt"
    with open(summary_path, "w", encoding="utf-8") as out:
        out.write(
            "Estimated unique rejected mails to cug@gekychat.com\n"
            "Rule: new unique mail if gap > 30 minutes for same sender\n"
            "Note: subject/body not available (rejected before DATA)\n\n"
        )
        for sender in SENDERS:
            items = [c for c in clusters if c["from"] == sender]
            out.write(f"{sender}: {len(items)} unique ({sum(c['n'] for c in items)} attempts)\n")
            for i, cluster in enumerate(items, 1):
                out.write(
                    f"  {i:3d}. {cluster['first']}  attempts={cluster['n']}  "
                    f"host={cluster['host']}  ip={cluster['ip']}\n"
                )
            out.write("\n")
        out.write(f"TOTAL estimated unique mails: {len(clusters)}\n")

    print(f"clusters={len(clusters)}")
    print(f"wrote {csv_path}")
    print(f"wrote {summary_path}")


if __name__ == "__main__":
    main()
