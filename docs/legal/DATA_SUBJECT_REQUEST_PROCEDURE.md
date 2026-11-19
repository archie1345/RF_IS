# PROSEDUR PENYAJUAN DATA PEMILIK — RF_IS

[DRAFT — BELUM DILAHIRKAN PEMANGKUAN]

Last Updated: 2026-11-02

## 1. UNGKAPAN

### 1.1 Definisi
- **Data-Pemilik = Individu yang diproses datanya di RF_IS.
- **Permintaan = Permintaan resmi data pemilik.
- **Wali = Orang tua/wali hukum yang mewakili anak di bawah umur.

## 2. DASAR HUKUM

| Hak | Pasal UU PDP | Batas |
|-----|--------------|-------|
| Akses data pribadi | Art. 32 | Segera setelah verifikasi identitas |
| Koreksi data tidak akurat | Art. 30 | Segera setelah verifikasi |
| Pengunduran persenjana | Art. 40 | Segera setelah verifikasi |
| Batasi pemrosesan | Art. 41 | Segera setelah verifikasi |
| Hentikan pemrosesan | Art. 42 | Segera setelah verifikasi |
| Penghancuran data | Art. 43-45 | Sesuai ketentuan hukum |

## 3. PROSEDUR PENYAJAAN

### 3.1 Penerimaan Permintaan
1. **Tautan**: Email, formulir, admin_panel, atau portal.
2. **Catat**: `request_id` (unik), `request_at`, `requestor_email`.
3. **Periksa**: `requestor` memiliki akses yang sah (admin atau data pemilik).

### 3.2 Verifikasi Identitas
- **Data Pemilik Aktif**: Verifikasi email, ID unik di aplikasi, dan akses akun yang sah.
- **Permintaan dari Wali**:
  - Verifikasi `guardian_relationship` aktif.
  - Verifikasi `athletes.age ≤ 18`.
  - Periksa: `guardian_id` dan `athletes.user_id` terhubung di database.
- **Permintaan dari Atlet**:
  - Verifikasi `user_id` terkait dengan akun aktif.
  - Jika akun tidak aktif: verifikasi bahwa atlet memiliki hak berdasarkan kebijakan penyimpanan.

### 3.3 Identifikasi Data
| Jenis Permintaan | Data yang Teridentifikasi |
|-----------------|---------------------------|
| Akses data pribadi | Data profil, kecapaian, kehadiran, pembayaran, media |
| Koreksi data | Data spesifik yang dilaporkan tidak akurat |
| Pengunduran persenjana | Kebijakan sesuai (TOS, Privasi, Wali, Media) |
| Batasi pemrosensing | Jenis pemrosesan yang dibatasi |
| Hentikan pemrosesan | Tujuan pemrosesan yang dihentikan |
| Penghancuran data | Sesuai kebijakan penyimpanan |

### 3.4 Evaluasi Tanggung Jawab Retensi
1. **Identifikasi**: Data yang diminta.
2. **Periksa**: Retensi kebijakan organisasi (Art. 24.5, 24.6).
3. **Evaluasi**: Tanggung jawab penyimpanan UU PDP.
4. **Tentukan**: Konsekuensi jika ada konflik.

### 3.5 Konsekuensi Penyimpanan vs Hak Data Pemilik
| Skenario | Penanganan |
|----------|------------|
| Konflik dengan retensi | Simpan, eskalasi manajemen/legal, keputusan tertulis |
| Tidak ada konflik | Terapkan segera sesuai permintaan |

### 3.6 Tanggung Jawab Manajemen
1. **Direktur Utama**: Persetujuan yang mengubah retensi data.
2. **Legal**: Menilai konsekuensi hukum retensi.
3. **Pengelola**: Melaksanakan keputusan yang disetujui.

### 3.7 Waktu Balas
- **Tanggapal**: Segera (UU PDP Art. 32, 40, 46: 3×24 jam).
- **Eksplanasi**: Tautan penjelasan.
- **Waktu**: Tanggal + waktu balasan.

## 4. PROSEDUR IMPLEMENTASI

### 4.1 Akses Data
1. **Verifikasi**: Identitas data pemilik atau wali (jika relevan).
2. **Periksa**: Batas akses (role).
3. **Implementasi**: Berikan akses sesuai permintaan.
4. **Log**: `request_access_granted` (event).

### 4.2 Koreksi Data
1. **Verifikasi**: Permintaan dan identifikasi data.
2. **Periksa**: Data akurat (bukti yang diberikan data pemilik/wali).
3. **Implementasi**: Update data sesuai permintaan.
4. **Log**: `request_correction_granted` (event).

### 4.3 Pengunduran Persenjana
1. **Verifikasi**: Identitas data pemilik/wali.
2. **Periksa**: `accept` status di database.
3. **Implementasi**: Set `accepted` = false.
4. **Notifikasi**: Beritahu data pemilik/wali.
5. **Log**: `request_withdraw_consent_granted` (event).

### 4.4 Batasi Pemrosesan
1. **Verifikasi**: Permintaan dan identifikasi data.
2. **Implementasi**: Batasi pemrosesan sesuai kebijakan.
3. **Log**: `request_restriction_granted` (event).

### 4.5 Hentikan Pemrosesan
1. **Verifikasi**: Permintaan dan identifikasi data.
2. **Evaluasi**: Retensi hukum dan kepentingan organisasi.
3. **Implementasi**: Hentikan pemrosesan.
4. **Log**: `request_termination_granted` (event).

### 4.6 Penghancuran Data
1. **Verifikasi**: Permintaan dan identifikasi data.
2. **Periksa**: Retensi hukum (UU PDP Art. 43-45).
3. **Evaluasi**: Kepentingan organisasi vs retensi hukum.
4. **Eksplikasi**: Penanganan konflik ke manajemen/legal.
5. **Implementasi**: Sesuai keputusan manajemen/legal.
6. **Log**: `request_deletion_granted` (event).

## 5. PELALUHAN

| Data | Retensi Kebijakan Organisasi |
|------|-----------------------------|
| Kecepatan | Selalu dipertahankan (Art. 24.5, 24.6) |
| Pembayaran | Selalu dipertahankan (Art. 24.5, 24.6) |
| Media pribadi | Selalu dipertahankan di sistem, tidak dipublikasikan |
| Profil aktif | Disimpan selama keanggotaan aktif |
| Data sensitif (kesehatan, keuangan) | Dipertahankan selama diperlukan, sesuai UU PDP |
| Log keamanan | Sesuai kebijakan manajemen |

## 6. PERINTAHAN

- Tidak ada tugas otomat yang menghapus data.
- Semua permintaan memerlukan verifikasi dan persetujuan (kecuali yang diatur secara eksplisit).
- Semua konflik disimpan untuk peninjauan manajemen/legal.
- Semua tindakan dicatat di audit trail.

## 7. KESIMPULAN

- Semua permintaan dicatat dan diverifikasi.
- Permintaan yang masuk akal dilaksanakan sesuai retensi kebijakan.
- Konflik disimpan untuk resolusi manajemen/legal.
- Semua tindakan dicatat.

[STATUS: BELUM DILAHIRKAN — PERLU PERHATIAN MANAJEMEN]

---
Dokumen ini adalah DRAFT. Perlu persetujuan resmi dari manajemen organisasi sebelum dapat digunakan.
