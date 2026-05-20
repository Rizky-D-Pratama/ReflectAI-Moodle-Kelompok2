# ReflectAI: Plugin Refleksi Pembelajaran Otomatis Berbasis Generative AI untuk Moodle

ReflectAI adalah plugin lokal Moodle yang secara otomatis menghasilkan refleksi pembelajaran berbasis AI setiap kali siswa mengumpulkan tugas (assignment). Refleksi ditampilkan langsung di halaman assignment siswa tanpa intervensi guru, serta dapat dipantau dan diberi catatan oleh guru melalui halaman monitoring khusus.

## Fitur Utama

- Refleksi otomatis saat siswa mengumpulkan tugas
- Panel status real-time: belum submit, sedang diproses, selesai, atau error
- Halaman monitoring guru untuk memantau semua hasil refleksi siswa per assignment
- Catatan guru — guru dapat menambahkan komentar yang ditampilkan di panel siswa
- Deteksi relevansi submission terhadap deskripsi tugas
- Mendukung berbagai format file: PDF, DOCX, file teks, dan gambar

## Persyaratan

- Moodle **5.1.3** atau lebih baru
- PHP **8.1** atau lebih baru dengan ekstensi: `curl`, `zip`, `dom`, `libxml`
- [Ollama](https://ollama.com) berjalan secara lokal dengan model `gemma3`
- Library `smalot/pdfparser` terinstall via Composer untuk ekstraksi teks PDF

## Instalasi Plugin

1. Salin folder `local/ai_reflection` ke direktori `local/` di instalasi Moodle kamu
2. Login sebagai admin, jalankan upgrade
3. Ikuti instruksi di layar untuk menyelesaikan upgrade database

## Setup Ollama

1. Install Ollama dari https://ollama.com
2. Pull model yang digunakan:
```
ollama pull gemma3
```
3. Pastikan Ollama berjalan di `http://localhost:11434`

## Setup Composer & PDF Parser

Jalankan perintah berikut di root folder Moodle:
```
composer require smalot/pdfparser
```

## Konfigurasi Cron

Plugin ini menggunakan sistem adhoc task Moodle untuk memproses refleksi di background. Moodle cron harus berjalan agar refleksi diproses secara otomatis.

### Hanya menjalankan task AI Reflection

**Windows (PowerShell)**
```
while ($true) { & "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle\admin\cli\adhoc_task.php" --classname="local_ai_reflection\task\process_submission_task" --force; Start-Sleep 10 }
```

**Linux / Mac (Terminal)**
```
while true; do php /path/to/moodle/admin/cli/adhoc_task.php --classname="local_ai_reflection\task\process_submission_task" --force; sleep 10; done
```

### Menjalankan semua cron Moodle

**Server Linux (cron job — jalankan setiap 1 menit)**
```
* * * * * /usr/bin/php /path/to/moodle/admin/cli/cron.php > /dev/null 2>&1
```

**Windows (PowerShell)**
```
while ($true) { & "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle\admin\cli\cron.php"; Start-Sleep 30 }
```

**Linux / Mac (Terminal)**
```
while true; do php /path/to/moodle/admin/cli/cron.php; sleep 30; done
```

> **Catatan:** Tanpa cron yang berjalan, task analisis AI akan masuk antrian tetapi tidak dieksekusi secara otomatis.

## Cara Kerja

```
Siswa submit tugas
       ↓
Event assessable_submitted terpicu
       ↓
Observer membuat record dengan status "processing" di database
       ↓
Adhoc task di-queue
       ↓
Moodle Cron menjalankan task
       ↓
Payload diekstrak (teks, gambar, konteks assignment)
       ↓
Payload dikirim ke Ollama (model gemma3)
       ↓
Hasil refleksi disimpan ke database
       ↓
Panel siswa otomatis menampilkan hasil refleksi
```

## Format File yang Didukung

| Format | Dukungan |
|---|---|
| PDF (berbasis teks) | Teks diekstrak penuh |
| PDF (teks + gambar) | Teks diekstrak, gambar disebutkan sebagai keterbatasan |
| PDF (scan/gambar saja) | Keterbatasan visual dilaporkan |
| DOCX (teks) | Teks diekstrak penuh |
| DOCX (teks + gambar) | Teks diekstrak, gambar dikirim ke AI jika model mendukung vision |
| Gambar (JPG, PNG, dll) | Dikirim ke AI jika model mendukung vision |
| TXT, MD, CSV, JSON, XML | Teks diekstrak penuh |

## Keterbatasan

- Membutuhkan Ollama yang berjalan di server yang sama dengan Moodle
- Kualitas refleksi bergantung pada kemampuan model AI yang digunakan (`gemma3` secara default)
- Analisis gambar/visual membutuhkan model yang mendukung multimodal (vision); dapat timeout pada perangkat dengan resource terbatas
- Pemrosesan refleksi tidak real-time — bergantung pada interval cron yang dikonfigurasi
- Hanya mendukung Moodle 5.1 ke atas (menggunakan Hook API yang diperkenalkan di Moodle 5.1)
- Setiap submission menghasilkan satu refleksi; resubmit akan menggantikan refleksi sebelumnya

## Lisensi

Plugin ini dilisensikan di bawah [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html), sesuai dengan ketentuan lisensi Moodle.
