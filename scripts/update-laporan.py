#!/usr/bin/env python3
"""Membangun ulang LAPORAN-KERJA.md dari riwayat git.

Dipanggil otomatis oleh git hook `post-commit` setiap ada commit baru.
Menghasilkan laporan dengan ringkasan per seksi + tautan bukti GitHub.
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

# Kata kunci dalam pesan commit → kategori bisnis untuk ringkasan otomatis
KATEGORI = [
    (["invoice", "proforma", "watermark", "dp ", "paid", "deposit"],    "Invoice & Tagihan Customer"),
    (["keuangan", "finance", "ledger", "journal", "neraca", "l/r"],     "Laporan Keuangan"),
    (["quotation", "penawaran", "word", "pdf"],                          "Quotation & Dokumen"),
    (["tour", "itinerary", "booking", "assignment"],                     "Manajemen Tour"),
    (["mice", "event", "venue"],                                         "MICE / Event"),
    (["kode", "code", "type", "hotel", "rental", "guide", "ticketing"],  "Jenis & Kode Order"),
    (["sales", "supplier", "customer", "role", "akses"],                 "Akses & Pengguna"),
    (["laporan", "report", "auto", "hook", "push"],                      "Sistem & Otomasi"),
    (["fix", "perbaik", "bug", "error"],                                 "Perbaikan"),
]

# Awalan teknis pada pesan commit → frasa yang lebih ramah atasan
PREFIX = {
    "Fix ":      "Perbaikan — ",
    "fix ":      "Perbaikan — ",
    "Redesain ": "Perbaikan tampilan — ",
    "Add ":      "Menambah ",
    "Tambah ":   "Menambah ",
}

# Deskripsi dampak bisnis per keyword — ditambahkan sebagai poin di bawah judul
DAMPAK = {
    "watermark":    "PDF invoice kini otomatis menampilkan status pembayaran (DEPOSIT RECEIVED / PAID IN FULL).",
    "multi-currency": "Invoice bisa diterbitkan dalam mata uang asing (USD, EUR, SGD, dll); Keuangan tetap IDR.",
    "proforma":     "Sales bisa mengisi deskripsi layanan (Hotel, Transport, dll) langsung di form invoice.",
    "kode tour":    "Kode order kini berbasis tipe (Inbound, Outbound, Hotel, dll) agar mudah diidentifikasi.",
    "laporan kerja": "Laporan kerja otomatis dibuat dan di-push ke GitHub setiap ada penyimpanan perubahan.",
    "deposit":      "Sales bisa mencatat uang muka (DP) langsung dari halaman tour.",
    "english":      "Invoice yang dikirim ke pelanggan menggunakan bahasa Inggris sepenuhnya.",
}


def git_log():
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
    return f"{HARI[int(u) - 1]}, {int(d)} {BULAN[int(m)]} {y}"


def kategori_commit(subject):
    sl = subject.lower()
    for keywords, label in KATEGORI:
        if any(k in sl for k in keywords):
            return label
    return "Pengembangan Sistem"


def dampak_baris(subject):
    sl = subject.lower()
    for keyword, keterangan in DAMPAK.items():
        if keyword in sl:
            return keterangan
    return None


def build():
    # groups: ymd -> { u, items: [(hash, subject, raw_subject)] }
    groups = OrderedDict()
    for ln in git_log():
        datepart, hash_, raw_subject = ln.split("\t", 2)
        ymd, u = datepart.split(" ")
        g = groups.setdefault(ymd, {"u": u, "items": []})
        g["items"].append((hash_, pretty_subject(raw_subject), raw_subject))

    today    = date.today()
    hari_ini = f"{int(today.strftime('%d'))} {BULAN[today.month]} {today.year}"

    out = [
        "# Laporan Kerja — Sistem ERP Welcome Manado",
        "",
        f"> Diperbarui otomatis dari riwayat pengembangan setiap ada perubahan. Terakhir: {hari_ini}",
        "",
        "---",
        "",
    ]

    for ymd, g in groups.items():
        out.append(f"## {id_date(ymd, g['u'])}")
        out.append("")

        # Kelompokkan commit per kategori untuk hari ini
        kat_groups: dict[str, list] = OrderedDict()
        for hash_, subject, raw in reversed(g["items"]):
            k = kategori_commit(raw)
            kat_groups.setdefault(k, []).append((hash_, subject, raw))

        # Ringkasan hari (daftar kategori unik)
        kategori_list = ", ".join(kat_groups.keys())
        out.append(f"**Fokus hari ini:** {kategori_list}.")
        out.append("")

        # Satu seksi per kategori
        for i, (kat, items) in enumerate(kat_groups.items(), 1):
            out.append(f"### {i}. {kat}")
            out.append("")

            hashes = [h for h, _, _ in items]

            # Poin pekerjaan
            for _, subject, raw in items:
                out.append(f"- {subject}")
                d = dampak_baris(raw)
                if d:
                    out.append(f"  _{d}_")

            out.append("")

            # Tautan bukti
            if len(hashes) == 1:
                url = f"{GITHUB_URL}/commit/{hashes[0]}"
                out.append(f"**Bukti pengerjaan:** [Lihat perubahan di GitHub →]({url})")
            else:
                links = ", ".join(
                    f"[perubahan {j+1}]({GITHUB_URL}/commit/{h})"
                    for j, h in enumerate(hashes)
                )
                out.append(f"**Bukti pengerjaan:** {links}")

            out.append("")
            out.append("---")
            out.append("")

    with open(OUT, "w", encoding="utf-8") as f:
        f.write("\n".join(out).rstrip() + "\n")


if __name__ == "__main__":
    build()
