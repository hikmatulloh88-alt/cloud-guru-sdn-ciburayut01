# Panduan Pembaruan UI & UX — Cloud Guru SDN Ciburayut 01

Paket ini berisi pembaruan antarmuka dan pengalaman pengguna (*ease of use*) untuk repositori `hikmatulloh88-alt/cloud-guru-sdn-ciburayut01`.

## Daftar File yang Diperbarui
1. `includes/sidebar.php` — Sidebar terpadu dengan tipografi **Plus Jakarta Sans**, ikon vektor SVG, tampilan nama lengkap guru, menu **AI Studio**, serta tautan cepat ke **Portal Sekolah**.
2. `dashboard/index.php` — Dashboard interaktif dengan sapaan otomatis berdasarkan waktu, tanggal bahasa Indonesia, status koneksi **Google Drive** secara langsung, serta kartu akses cepat ke seluruh ruang kerja.
3. `storage/index.php` — Terintegrasi dengan sidebar utama, dilengkapi area **Drag & Drop Upload**, pratinjau ukuran file sebelum unggah, indikator progres unggah, serta **pencarian instan & filter jenis file** (Dokumen/PDF, Excel/Slide, Gambar).
4. `ai-studio/index.php` — Terintegrasi dengan sidebar utama, desain terang modern yang selaras dengan portal, kotak seret-lepas foto, **tombol preset instruksi cepat** untuk guru, dan indikator progres pembuatan video.
5. `auth/login.php` & `auth/login.css` — Tambahan tombol **Lihat/Sembunyikan Password**, tautan kembali ke Portal Sekolah, dan indikator proses saat tombol Masuk ditekan.
6. `assets/css/glass-ui.css` — Penyelarasan gaya *frosted glass* agar konsisten di seluruh halaman.
7. `.cpanel.yml` — Penambahan sinkronisasi `auth/login.css` dan `ai-studio/download.php` saat deployment otomatis cPanel.

## Cara Menerapkan ke GitHub & Server
1. Ekstrak seluruh isi file ZIP ini ke folder lokal repositori `cloud-guru-sdn-ciburayut01` (timpa file lama yang sesuai jalurnya).
2. Periksa perubahan dengan `git status` dan `git diff`.
3. Commit dan push ke branch utama GitHub (`main`), atau buka Pull Request agar workflow validasi sintaks PHP (`self-healing-validate.yml`) berjalan otomatis.
4. Tidak ada perubahan struktur tabel database yang diperlukan—semua modul tetap kompatibel dengan database Absensi, Google Drive OAuth, dan cPanel yang sudah berjalan.
