<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Reflection';
// Capabilities
$string['ai_reflection:viewresults'] = 'Lihat hasil AI Reflection';
$string['ai_reflection:addteachernote'] = 'Tambah catatan guru pada AI Reflection';
// Settings
$string['setting_ollamaurl'] = 'URL Ollama';
$string['setting_ollamaurl_desc'] = 'URL server Ollama yang digunakan untuk memproses refleksi AI. Contoh: http://localhost:11434';
$string['setting_ollamamodel'] = 'Nama Model Ollama';
$string['setting_ollamamodel_desc'] = 'Nama model Ollama yang digunakan. Contoh: gemma3, llama3, mistral';
$string['setting_ollamatimeout'] = 'Timeout Request (detik)';
$string['setting_ollamatimeout_desc'] = 'Batas waktu maksimal untuk menunggu respons dari Ollama. Naikkan nilai ini jika sering terjadi timeout pada file besar.';
$string['setting_ollamabatchsize'] = 'Ukuran Batch Gambar';
$string['setting_ollamabatchsize_desc'] = 'Jumlah gambar yang dikirim ke Ollama dalam satu request. Turunkan nilai ini jika sering terjadi timeout saat memproses gambar.';