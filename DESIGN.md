---
name: PORSELA Design System
description: Identitas editorial komunitas, diekstrak dari Home terbaru.
colors:
  primary: "#173f35"
  primary-hover: "#265648"
  accent: "#e9af58"
  background: "#f7f5ed"
  surface: "#eeeee3"
  surface-raised: "#fffdf7"
  text: "#273a32"
  muted: "#58665d"
  border: "#d4d8cb"
  input-border: "#87938a"
  focus: "#a15b15"
  success-bg: "#e3ece1"
  success-text: "#254e35"
  warning-bg: "#f5e6c9"
  warning-text: "#775116"
  danger-bg: "#f4e2db"
  danger-text: "#8d3425"
  danger: "#953c2e"
  dark-background: "#15251e"
  dark-surface: "#203329"
  dark-surface-raised: "#1a2c23"
  dark-primary: "#acd0b9"
  dark-text: "#f0f0e5"
  dark-muted: "#b4c3b6"
typography:
  display:
    fontFamily: "Georgia, Times New Roman, serif"
    fontSize: "5.75rem"
    fontWeight: 400
    lineHeight: 1.08
    letterSpacing: "-0.04em"
  headline:
    fontFamily: "Georgia, Times New Roman, serif"
    fontSize: "3rem"
    fontWeight: 400
    lineHeight: 1.2
    letterSpacing: "-0.03em"
  title:
    fontFamily: "Segoe UI, Tahoma, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "Segoe UI, Tahoma, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.65
  label:
    fontFamily: "Segoe UI, Tahoma, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.65
rounded:
  sm: "4px"
  badge: "3px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "24px"
  xl: "32px"
  section: "48px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.background}"
    rounded: "{rounded.sm}"
    padding: "12px 20px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
  button-accent:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.primary}"
    rounded: "{rounded.sm}"
  input:
    backgroundColor: "{colors.surface-raised}"
    textColor: "{colors.text}"
    rounded: "{rounded.sm}"
    padding: "12px 14px"
---

# PORSELA

## Overview

Sistem ini mengikuti Home PORSELA yang sudah didesain ulang: hangat, humanis, tepercaya, dan jelas. Kesan editorial dibangun melalui heading serif, foto lokal, ruang yang proporsional, dan aksen amber terbatas. Halaman operasional memakai identitas yang sama dengan kepadatan yang sesuai untuk mengelola donasi, klaim, dan akun.

Sumber implementasi token adalah `assets/css/style.css`; `assets/css/home.css` hanya memuat komposisi khusus Home dan alias token bersama. Gunakan HTML, CSS, PHP native, dan JavaScript tanpa dependency tambahan.

## Colors

Hijau hutan adalah warna brand dan aksi utama. Krem adalah latar terang; amber untuk aksen dan ajakan berbagi. Warna status selalu disertai teks. Token semantik `--primary`, `--bg`, `--surface`, `--text`, dan token status berubah pada `body.dark`.

Tema berasal dari cookie melalui `themeClass()`. Jangan mengganti mekanismenya dengan preferensi sistem atau penyimpanan JavaScript. Tema gelap memakai latar hijau pekat, permukaan yang lebih terang, teks putih hangat, dan primary sage. `--brand` tetap hijau hutan untuk blok cerita Home; latar blok tersebut selalu dipasangkan dengan teks terang.

Border pemisah memakai `--border`; border kontrol memakai `--input-border` yang lebih jelas. Focus ring memakai `--focus` dengan outline 3px dan offset 4px. Nilai hex dipertahankan dari implementasi, tanpa konversi yang mengubah warna Home.

## Typography

Georgia untuk judul halaman, section, dan angka dampak; Segoe UI dengan Tahoma/sans-serif untuk isi, kontrol, label, dan judul rekaman. Font mengikuti stack Home yang sudah tersedia dan tidak memerlukan unduhan.

| Peran | Ukuran | Line-height | Bobot |
| --- | --- | --- | --- |
| Hero Home | Responsif, maksimum 5.75rem | 1.08 | 400 |
| Judul halaman | 2–3rem | 1.2 | 400 |
| Judul section | 1.5–2rem | 1.25 | 400 |
| Judul rekaman | 1.25rem | 1.4 | 600 |
| Isi dan input | 1rem | 1.65 / input 1.5 | 400 |
| Label dan metadata | 0.875rem | 1.65 | 400–600 |
| Caption dan helper | 0.8125rem | 1.6 | 400 |

Satu H1 per halaman. Teks pengguna boleh membungkus; jangan memotong informasi donasi atau catatan penerimaan. Gunakan tabular numerals pada angka yang dibandingkan. Ukuran operasional mengikuti skala token yang sudah diimplementasikan, tanpa memaksakan rasio baru pada Home.

## Layout

Lebar maksimum konten 1224px. Margin horizontal desktop 3rem, tablet 1.5rem, dan mobile 1.25rem. Breakpoint utama: 1100px, 800px, 540px; penyesuaian Home sempit pada 359px. Spacing dibangun dari kelipatan 4px dengan interval 8/12/16/24/32/48/64px.

Navigasi ada di atas dan mengikuti alur dokumen. Dashboard memakai grid rekaman, tanpa sidebar. Form tambah donasi berada dalam `details` pada halaman donatur, otomatis terbuka ketika ada feedback server. Pengajuan klaim memakai disclosure pada makanan terkait. Kategori memakai form tambah tersendiri dan form edit pada rekamannya. Profil memakai dua kolom yang menjadi satu kolom di tablet.

Statistik dampak adalah baris tipografi terbuka. Tabel numerik rata kanan dan kategori rata kiri; tabel membungkus teks pada layar sempit. Grid memakai `minmax(min(100%,320px),1fr)` untuk mencegah overflow. Tinggi rekaman mengikuti isinya, tanpa ruang kosong yang dipaksakan.

## Elevation & Depth

Tidak ada shadow dekoratif. Pengelompokan memakai perbedaan permukaan atau border 1px. Form memakai latar tonal tanpa border kotak tambahan; rekaman memakai border. Bagian audit admin memakai pemisah baris dan latar halaman.

## Shapes

Radius utama 4px mengikuti Home; badge 3px. Status tidak memakai pill besar. Foto makanan memiliki tinggi konsisten dan `object-fit: cover`; foto gagal dimuat diganti placeholder bertulisan. Bukti penerimaan dan catatannya tetap ditampilkan ketika tersedia.

## Components

- **Shell:** `includes/navigation.php` dan `includes/footer.php` digunakan semua halaman UI. Link mengikuti session dan role, memakai `roleHome()` serta token logout yang lama. Halaman aktif memakai `aria-current="page"`. Username dan role selalu tersedia.
- **Menu mobile:** tombol native, `aria-expanded` dan `aria-controls`; Enter/Space membuka, Escape menutup dan mengembalikan fokus. Tanpa JavaScript, seluruh link tetap tampil.
- **Button:** `.btn-primary` untuk aksi utama, `.btn-outline`/`.btn-secondary` untuk aksi pendukung, `.btn-danger` untuk aksi penolakan/hapus. Tinggi minimum 44px, default 48px, CTA Home 52px. Jangan mengubah value/name field saat menambah feedback submit.
- **Form:** label membungkus kontrol atau memakai `for` yang sudah ada. Nama, ID, hidden field, CSRF, enctype, batas input, dan validasi backend harus dipertahankan. Input minimal 48px dan font 16px. Preview file lokal tidak mengganti validasi atau proses upload.
- **Feedback:** error memakai `role="alert"`, sukses memakai `role="status"`. Form memberi label tombol “Memproses…” setelah validasi browser dan confirmation lolos; tidak menghapus data submit. State pulih setelah bfcache.
- **Confirmation:** dialog konfirmasi hapus yang sudah ada tetap bekerja; pembatalan tidak mengirim form.
- **Filter:** pertahankan `ls-q`, `ls-cat`, `ls-del`, `ls-count`, `ls-empty`, seluruh `data-food-card`, `data-title`, `data-cat`, `data-del`, dan `live-search.js`. Hasil pencarian memakai atribut `hidden`.
- **Motion:** transisi 180ms ease-out untuk kontrol; hover gambar Home 250ms. Tidak ada reveal massal atau animasi otomatis. `prefers-reduced-motion` menonaktifkan motion.

## Do's and Don'ts

- Do pertahankan komposisi dan identitas Home; adaptasikan kepadatan untuk fungsi halaman.
- Do gunakan token semantik agar tema terang dan gelap konsisten.
- Do uji navigasi keyboard, label, data kosong, teks panjang, foto hilang, dan lebar 320px.
- Don't tambahkan sidebar SaaS, gradient dekoratif, glow, kartu bertumpuk, atau library UI.
- Don't ubah query, autentikasi, session, CSRF, proses upload, kondisi status, dan otorisasi sebagai bagian dari styling.

Verifikasi 23 November 2026: seluruh halaman dirender pada Chrome dalam dua tema, lebar 320/390/768/1440px. Pemeriksaan lintas browser dan alur tulis data produksi memerlukan pengujian terpisah.
