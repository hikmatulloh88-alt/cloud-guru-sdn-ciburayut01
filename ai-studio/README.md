# Cloud Guru AI Studio — patch terisolasi

Modul ini menambahkan halaman AI foto menjadi video di `/ai-studio/` menggunakan fal.ai Kling 2.6 Pro (durasi 5/10 detik). Modul dibuat terpisah; tidak mengubah `index.php`, database sekolah, absensi, atau Google Drive.

## Konfigurasi API key
Pilih salah satu cara:
1. Set environment variable `FAL_KEY` pada hosting (paling disarankan); atau
2. Buat file `config/ai_video.php` di server saja (JANGAN commit file berisi key), dengan isi:
   ```php
   <?php
   return ['fal_key' => 'ISI_API_KEY_DI_SERVER'];
   ```

## Prasyarat
- HTTPS aktif
- PHP dengan cURL dan fileinfo
- Batas unggah PHP cukup besar (minimal 16 MB)
- Akun fal.ai dan saldo/kredit yang memadai

Dokumentasi model: https://fal.ai/models/fal-ai/kling-video/v2.6/pro/image-to-video/api

## Penting sebelum deploy
- Halaman dan endpoint memakai session guard yang mengenali beberapa nama session umum. Karena source login/dashboard internal tidak terlihat di root repo, cocokkan guard dengan nama session yang benar pada instalasi Cloud Guru. Jangan publikasikan endpoint sebelum guard diverifikasi.
- Endpoint memakai request sinkron, batas timeout hosting dapat membuat permintaan panjang gagal. Bila hosting membatasi waktu, implementasikan queue/status polling sesuai protokol fal.ai.
- Tautan video yang dikembalikan berasal dari penyedia AI; atur penyimpanan/retensi sesuai kebutuhan sekolah.
- Polling halaman menunggu maksimal sekitar 6 menit. Bila proses AI lebih lama, halaman berhenti menunggu; proses dapat tetap berjalan di penyedia AI.
- Ini patch sumber awal, belum diuji di hosting, belum terhubung otomatis ke sidebar, dan bukan APK Android yang sudah dibangun.
