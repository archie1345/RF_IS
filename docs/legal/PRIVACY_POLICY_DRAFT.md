# KEBIJAKAN KEPRIBADIAN (DRAFT — PERLU PERSAMAAN MANAJEMEN)

## 1. Identitas Organisasi

- Nama: Rhino Fighter Taekwondo Club / Academy (perlu verifikasi status hukum)
- Lokasi: Malang, Jawa Timur (alamat resmi perlu disetujui manajemen)
- Periode efektif: Tanggal [PASTE DATE]

## 2. Ruang Lingkup Kebijakan Ini Berlaku Untuk

Semua data pribadi yang diproses melalui RF_IS meliputi:

1. Data peserta latihan (nama, tanggal lahir, alamat, kontak)
2. Data orang tua/wali hukum (kontak, relasi, persetujuan)
3. Data keikutsertaan kompetisi & pencapaian
4. Data pembayaran & transaksi keuangan
5. Data foto/video atlet dan dokumentasi organisasi
6. Log aktivitas sistem dan bukti keamanan

## 3. Dasar Pemrosesan Data

Pemrosesan data dilakukan atas dasar:

1. Persetujuan eksplisit peserta atau wali hukum (UU PDP Pasal 20-24)
2. Kepentingan sah organisasi untuk mengelola kegiatan taekwondo
3. Pelaksanaan izin penyelenggara sistem elektronik (PSE) — status belum dikonfirmasi

## 4. Kategori Data yang Diproses

| Kategori | Apakah Anak | Akses Diperbolehkan | Penyimpanan |
|----------|-------------|---------------------|-------------|
| Identitas atlet (nama, DoB) | Ya | Atlet + wali hukum + staf berwenang | Terenkripsi di DB |
| Kontak darurat | Ya | Wali hukum eksklusif | Terenkripsi di DB |
| Pembayaran | Tidak | Admin/keuangan + akun terkait | Arsip terpisah dari profil aktif |
| Foto & video | Ya | Staf berwenang + atlet sendiri (jika ditambahkan persetujuan) | Penyimpanan pribadi, bukan publik |

## 5. Hak Data Terganti

Setiap peserta atau wali berhak untuk:

1. Mengakses data pribadinya — respons dalam 3x24 jam (UU PDP Pasal 32)
2. Mengoreksi data tidak akurat — proses sesuai UU PDP Pasal 30
3. Menunda pemrosesan sementara — dapat mengajukan permintaan (Pasal 41)
4. Menghapus data (ketika tidak ada kewajiban retensi hukum) — kecuali prestasi & catatan keuangan yang diarsipkan

## 6. Penyimpanan & Retensi Data

- Data profil aktif: disimpan saat keanggotaan berlaku
- Prestasi & catatan pembayaran: tetap dipertahankan selamanya sebagai arsip institusi (keputusan manajemen)
- Log keamanan: retensi [PERLU POLICY — FLAG AS BLOCKER]
- Bukti persetujuan wali: dipertahankan sesuai keputusan organisasi

## 7. Keamanan Data

- Password di-hash dengan bcrypt/Argon2 (pasangan PHP, konfigurasi harus diverifikasi)
- Tidak ada data ditampilkan secara publik
- Media atlet tidak pernah dimuat ke halaman web umum
- Semua akses memerlukan autentikasi dan otorisasi yang sesuai

## 8. Pemutusan Hubungan — Penanganan Penghapusan Data

Ketika akun atlet dihapus:

1. Akun login dinonaktifkan dan token dicabut
2. Prestasi dipindahkan ke arsip sejarah (bukan dihapus)
3. Catatan pembayaran dipindahkan ke arsip terpisah (bukan dihapus)
4. Tidak ada cascade deletion yang menghapus data historis

## 9. Kontak Data Perlindungan

- Email: rhinofighter2023@gmail.com (perlu verifikasi status terkini)
- Telepon: 0812-3577-8181 (perlu verifikasi terkini)
- Notifikasi insiden keamanan wajib diberikan dalam 3x24 jam sesuai UU PDP Pasal 46

## 10. Informasi Tambahan

Kebijakan ini dapat diperbarui. Perubahan materiil akan dipublikasikan dan penerima notifikasi melalui email atau inisiasi ulang.

---

DRAFT — PERLU PERSAMAAN MANAJEMEN DAN VERIFIKASI HUKUM
Status: Belum disetujui — jangan disebarluaskan sebagai kebijakan final
