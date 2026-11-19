# KEBIJAKAN KEPERMINAN DAN PERSENJANA — RF_IS

[DRAFT — BELUM DILAHIRKAN PEMANGKUAN]

Last Updated: 2026-11-02

## 1. UNGKAPAN

### 1.1 Definisi
- **Persenjana** = Kelayakan atau pengakuan pengguna untuk pemrosesan data pribadi.
- **Kewenangan Guardian** = Hak perwakilan orang tua/wali hukum atas akun atlet muda.
- **Versi Dokumen** = Elemen terstruktur yang melacak perubahan pada dokumen kebijakan.
- **Penerimaan** = Indikasi persetujuan eksplisit melalui mekanisme yang berlaku.

### 1.2 Kepemilikan Data
| Kategori | Pemegang | Dasar Pemrosesan |
|----------|----------|------------------|
| Nama, alamat, kontak | Atlet/Orang tua | UU PDP Art. 20 |
| Data sensitif (kesehatan, keuangan) | Atlet/Orang tua | UU PDP Art. 20 |
| Data kompetisi/kecapaian | Atlet (sejarah) | Kebijakan Organisasi |
| Data guardian | Guardian (hak perwakilannya) | UU PDP Art. 22 |

## 2. JENIS PERSENJANA

### 2.1 Penerimaan Ketentuan Layanan
- **Fungsi**: Pengakuan syarat dan ketentuan.
- **Penerimaan**: Checkbox + teks "Saya menyetujui" + tanggal + waktu.
- **Pencatatan**: `users.tos_accepted` (bool, datetime, policy_version).

### 2.2 Penerimaan Kebijakan Privasi
- **Fungsi**: Pengakuan cara pemrosesan data pribadi.
- **Penerimaan**: Checkbox + teks "Saya memahami" + tanggal + waktu.
- **Pencatatan**: `users.privacy_accepted` (bool, datetime, policy_version).

### 2.3 Penerimaan Persenjana Wali
- **Fungsi**: Hak perwakilan orang tua/wali hukum atas akun atlet muda.
- **Persyaratan**:
  - Usia atlet ≤ 18 tahun (buktikan dengan tanggal lahir).
  - Tautan `guardian_relationship` dalam `GuardianRelationship` model.
  - Verifikasi hubungan kependudukan (pendaftaran, surat kuasa, dll).
- **Pencatatan**: `guardian_relationships` (athletes_id, guardians_id, authorized_at, revocation_date).

### 2.4 Penerimaan Media Pribadi
- **Fungsi**: Hak pemrosesan foto/video atlet di sistem.
- **Penerimaan**: Checkbox terpisah ("Saya setuju media atlet saya diproses untuk keperluan internal").
- **Pencatatan**: `users.media_consent` (bool, datetime, purpose, version).

### 2.5 Pengunduran Persenjana
| Hak | Dasar UU PDP | Waktu Balas |
|-----|--------------|-------------|
| Pengunduran persenjana | Art. 40 | Segera setelah verifikasi identitas |
| Pengunduran persenjana guardian | Art. 40 | Segera setelah verifikasi hubungan |
| Batas | Tidak ada batas | Tidak ada batas |

## 3. EVIDENSI PERSENJANA

### 3.1 Elemen Wajib Pencatatan
1. **Identitas Pengguna**: `user_id`, `full_name`, `email`.
2. **Identitas Atlet** (jika perlu): `athlete_id`, `full_name`, `dobi`.
3. **Identitas Wali** (jika perlu): `guardian_id`, `full_name`, `relasi`.
4. **Hubungan**: `guardian_relationship` object (nama, tanggal perwakilannya).
5. **Tipe Persenjana**: `tos`, `privacy`, `guardian`, `media`.
6. **Versi Dokumen**: `policy_version` saat penerimaan.
7. **Tanggal**: `accepted_at`.
8. **Waktu**: `accepted_at`.
9. **Metode Penerimaan**: `method` (login, admin_panel, api).

### 3.2 Verifikasi Kewenangan Wali
1. **Periksa**: `guardian_relationship` aktif dan `authorized_at` belum dicabut.
2. **Periksa**: `athletes.age ≤ 18`.
3. **Periksa**: `users.email` atlet dan wali tidak identik (jika tidak sama).
4. **Rekam**: `action_by` (`user_email` yang mengambil tautan).

### 3.3 Revokasi Kewenangan Wali
- **Wali** mengundurkan persenjana: API `guardians.revoke_authorization`.
- **Sistem**: Set `authorized_at` = `null`, log event ke audit trail.
- **Akibat**: Akses tautan tautan tautan tautan tautan tautan.

## 4. VERSI DAN JENIS DOKUMEN

### 4.1 Manajemen Versi
- **Format**: `docs/legal/PRIVACY_POLICY_V1.0.md`.
- **Perubahan Material**: Versi baru + notifikasi pengguna.
- **Perubahan Non-Material**: Update di tempat di tempat.
- **Arsip Versi Lama**: `docs/legal/archive/` (tidak diubah).

### 4.2 Versioning Format
```markdown
---
version: 1.0
effective_date: 2026-11-02
last_modified: 2026-11-02
---
```

## 5. PROSEDUR PENERAPAN

### 5.1 On-boarding Baru
1. Tampilkan: `PRIVACY_POLICY`, `TERMS_OF_SERVICE`.
2. Require: Checkbox "Saya menerima Ketentuan Layanan".
3. Require: Checkbox "Saya memahami Kebijakan Privasi".
4. Jika atlet muda: Require persetujuan wali.
5. Simpan: `tos_accepted`, `privacy_accepted` pada `users`.
6. Log: `persenjana_terima` ke audit trail.

### 5.2 Update Dokumen
1. Buat versi baru (increment version number).
2. Simpan versi baru di lokasi yang ditentukan.
3. Arsipkan versi lama di `archive/`.
4. Update tautan di aplikasi dengan `current_version`.
5. Log perubahan.

### 5.3 Pencabutan Persenjana
1. User/Wali memanggil `users.withdraw_consent(policy_type)`.
2. Sistem verifikasi `user_id` (untuk TOS/Privasi) atau `guardian_relationship` (untuk Wali).
3. Sistem simpan `withdrawn_at`.
4. Sistem set `accepted` = false.
5. Log event ke audit trail.

## 6. PEMANGKUAN DAN JALAN

1. **Direktur Utama**: Persetujuan dokumen akhir dan perubahan material.
2. **Pengurus**: Verifikasi keaslian penerimaan dan penyimpanan.
3. **Pengelola**: Penerimaan tautan harian dan notifikasi.
4. **Legal**: Review kepatuhan terhadap UU PDP dan PP No 17/2025.
5. **Pengelola**: Menyimpan log penerimaan.

## 7. KESETIWAAN PENGELOLA

| Keadaan | Tanggung Jawab |
|---------|----------------|
| Persenjana yang hilang | Pelacakan melalui audit log, pemulihan data |
| Revokasi tautan tidak sah | Audit verifikasi, restore jika ada kesalahan |
| Konflik dengan UU PDP | Tidak mengubah penerimaan, eskalasi legal |

## 8. KESIMPULAN

- Semua persenjana tercatat secara eksplisit dengan bukti.
- Versi dokumen terkelola dan dicatat.
- Persenjana dapat dicabut secara kapan saja.
- Sistem mencatat siapa dan kapan tindakan diambil.

[STATUS: BELUM DILAHIRKAN — PERLU PERHATIAN MANAJEMEN]

---
Dokumen ini adalah DRAFT. Perlu persetujuan resmi dari manajemen organisasi sebelum dapat digunakan.
