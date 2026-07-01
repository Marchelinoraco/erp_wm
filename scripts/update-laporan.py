#!/usr/bin/env python3
"""Membangun ulang LAPORAN-KERJA.md dari riwayat git.

Dipanggil otomatis oleh git hook `post-commit` setiap ada commit baru,
sehingga laporan untuk atasan selalu tersinkron dengan pekerjaan yang tercatat.
Bisa juga dijalankan manual:  python3 scripts/update-laporan.py
"""
import os
import subprocess
from collections import OrderedDict
from datetime import date

REPO       = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT        = os.path.join(REPO, "LAPORAN-KERJA.md")
GITHUB_URL = "https://github.com/Marchelinoraco/erp_wm"

HARI  = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"]
BULAN = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni",
         "Juli", "Agustus", "September", "Oktober", "November", "Desember"]

# Awalan teknis pada pesan commit -> frasa yang lebih ramah atasan.
PREFIX = {
    "Fix ": "Perbaikan — ",
    "fix ": "Perbaikan — ",
    "Redesain ": "Perbaikan tampilan — ",
    "Add ": "Menambah ",
    "Tambah ": "Menambah ",
}


def git_log():
    """Kembalikan list baris 'YYYY-MM-DD<SPACE>u<TAB>hash<TAB>subject'."""
    out = subprocess.check_output(
        ["git", "log", "--no-merges", "--pretty=%ad\t%h\t%s", "--date=format:%Y-%m-%d %u"],
        cwd=REPO, text=True,
    )
    return [ln for ln in out.splitlines() if "\t" in ln]


def pretty_subject(s):
    s = s.strip()
    for pre, repl in PREFIX.items():
        if s.startswith(pre):
            s = repl + s[len(pre):]
            break
    return s[:1].upper() + s[1:] if s else s


def id_date(ymd, u):
    y, m, d = ymd.split("-")
    hari = HARI[int(u) - 1]
    return f"{hari}, {int(d)} {BULAN[int(m)]} {y}"


def build():
    groups = OrderedDict()  # ymd -> {"u": u, "items": [(hash, label)]}
    for ln in git_log():
        datepart, hash_, subject = ln.split("\t", 2)
        ymd, u = datepart.split(" ")
        g = groups.setdefault(ymd, {"u": u, "items": []})
        g["items"].append((hash_, pretty_subject(subject)))

    today    = date.today()
    hari_ini = f"{int(today.strftime('%d'))} {BULAN[today.month]} {today.year}"

    out = [
        "# Laporan Kerja — Sistem ERP Welcome Manado",
        "",
        f"> Diperbarui otomatis dari riwayat pengembangan setiap ada perubahan. Terakhir: {hari_ini}",
        "",
    ]
    for ymd, g in groups.items():
        out.append(f"## {id_date(ymd, g['u'])}")
        out.append("")
        for hash_, item in reversed(g["items"]):
            link = f"{GITHUB_URL}/commit/{hash_}"
            out.append(f"- {item} — [lihat bukti pengerjaan]({link})")
        out.append("")

    with open(OUT, "w", encoding="utf-8") as f:
        f.write("\n".join(out).rstrip() + "\n")


if __name__ == "__main__":
    build()
