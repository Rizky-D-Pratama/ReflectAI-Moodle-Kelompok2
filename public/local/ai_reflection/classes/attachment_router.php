<?php
namespace local_ai_reflection;

defined('MOODLE_INTERNAL') || die();

use Smalot\PdfParser\Parser;

class attachment_router {

    public static function route(\stored_file $file): array {
        $filename = $file->get_filename();
        $mimetype = $file->get_mimetype();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $result = [
            'filename' => $filename,
            'mimetype' => $mimetype,
            'size' => (int)$file->get_filesize(),
            'kind' => 'unsupported',
            'extracted_text' => null,
            'images_base64' => [],
            'embedded_image_count' => 0,
            'has_embedded_images' => false,
            'note' => null,
        ];

        if (self::is_image($mimetype, $ext)) {
            $content = (string)$file->get_content();
            if ($content !== '') {
                $result['kind'] = 'image';
                $result['images_base64'] = [base64_encode($content)];
                $result['embedded_image_count'] = 1;
                $result['has_embedded_images'] = true;
            } else {
                $result['kind'] = 'image';
                $result['note'] = 'Image file kosong.';
            }
            return $result;
        }

        if (self::is_text_like($mimetype, $ext)) {
            $content = self::clean_text((string)$file->get_content());
            if ($content !== '') {
                $result['kind'] = 'text';
                $result['extracted_text'] = $content;
            } else {
                $result['kind'] = 'text';
                $result['note'] = 'File teks kosong.';
            }
            return $result;
        }

        if ($mimetype === 'application/pdf' || $ext === 'pdf') {
            $text = self::extract_pdf_text($file);
            $imagecount = self::detect_pdf_embedded_images((string)$file->get_content());

            if ($text !== '' && $imagecount > 0) {
                $result['kind'] = 'pdf_hybrid';
                $result['extracted_text'] = $text;
                $result['embedded_image_count'] = $imagecount;
                $result['has_embedded_images'] = true;
                $result['note'] = 'PDF berisi teks dan gambar. Teks berhasil diekstrak, tetapi bagian visual belum dianalisis.';
                return $result;
            }

            if ($text !== '') {
                $result['kind'] = 'pdf_text';
                $result['extracted_text'] = $text;
                return $result;
            }

            if ($imagecount > 0) {
                $result['kind'] = 'pdf_image_only';
                $result['embedded_image_count'] = $imagecount;
                $result['has_embedded_images'] = true;
                $result['note'] = 'PDF terdeteksi berisi gambar/scan tanpa teks bermakna. Konten visual belum dianalisis.';
                return $result;
            }

            $result['kind'] = 'pdf_unknown';
            $result['note'] = 'PDF ada, tetapi teksnya tidak berhasil diekstrak.';
            return $result;
        }

        if (
            $mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
            $ext === 'docx'
        ) {
            [$text, $images] = self::extract_docx_text_and_images($file);

            if ($text !== '' && !empty($images)) {
                $result['kind'] = 'docx_hybrid';
                $result['extracted_text'] = $text;
                $result['images_base64'] = $images;
                $result['embedded_image_count'] = count($images);
                $result['has_embedded_images'] = true;
                return $result;
            }

            if ($text !== '') {
                $result['kind'] = 'docx_text';
                $result['extracted_text'] = $text;
                return $result;
            }

            if (!empty($images)) {
                $result['kind'] = 'docx_image_only';
                $result['images_base64'] = $images;
                $result['embedded_image_count'] = count($images);
                $result['has_embedded_images'] = true;
                return $result;
            }

            $result['kind'] = 'docx_unknown';
            $result['note'] = 'DOCX ada, tetapi teks maupun gambar tidak berhasil diekstrak.';
            return $result;
        }

        $result['note'] = 'Tipe file ini belum diproses otomatis.';
        return $result;
    }

    private static function extract_pdf_text(\stored_file $file): string {
        try {
            self::ensure_composer_autoload();

            $parser = new Parser();
            $pdf = $parser->parseContent((string)$file->get_content());
            return self::clean_text((string)$pdf->getText());
        } catch (\Throwable $e) {
            debugging('PDF parser failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    private static function detect_pdf_embedded_images(string $rawpdf): int {
        if ($rawpdf === '') {
            return 0;
        }

        $count = 0;

        if (preg_match_all('/\/Subtype\s*\/Image\b/i', $rawpdf, $m)) {
            $count += count($m[0]);
        }

        if ($count === 0 && preg_match_all('/\/Type\s*\/XObject\b.*?\/Subtype\s*\/Image\b/is', $rawpdf, $m2)) {
            $count += count($m2[0]);
        }

        return $count;
    }

    private static function extract_docx_text_and_images(\stored_file $file): array {
        try {
            $tempdir = make_temp_directory('local_ai_reflection_docx');
            $docxpath = $tempdir . '/' . uniqid('docx_', true) . '.docx';

            file_put_contents($docxpath, $file->get_content());

            $zip = new \ZipArchive();
            $opened = $zip->open($docxpath);

            if ($opened !== true) {
                @unlink($docxpath);
                return ['', []];
            }

            $text = '';
            $images = [];

            $xml = $zip->getFromName('word/document.xml');
            if ($xml !== false && trim($xml) !== '') {
                $text = self::extract_text_from_docx_xml($xml);
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if (strpos($name, 'word/media/') === 0) {
                    $content = $zip->getFromIndex($i);
                    if ($content !== false && $content !== '') {
                        $images[] = base64_encode($content);
                    }
                }
            }

            $zip->close();
            @unlink($docxpath);

            return [$text, $images];
        } catch (\Throwable $e) {
            debugging('DOCX parser failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['', []];
        }
    }

    private static function extract_text_from_docx_xml(string $xml): string {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml)) {
            return '';
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = [];
        $nodes = $xpath->query('//w:p');

        if ($nodes === false) {
            return '';
        }

        foreach ($nodes as $pnode) {
            $textnodes = $xpath->query('.//w:t', $pnode);
            if ($textnodes === false) {
                continue;
            }

            $line = '';
            foreach ($textnodes as $tnode) {
                $line .= $tnode->nodeValue;
            }

            $line = self::clean_text($line);
            if ($line !== '') {
                $paragraphs[] = $line;
            }
        }

        return trim(implode("\n", $paragraphs));
    }

    private static function ensure_composer_autoload(): void {
        global $CFG;

        static $loaded = false;
        if ($loaded) {
            return;
        }

        $paths = [
            $CFG->dirroot . '/../vendor/autoload.php',
            dirname($CFG->dirroot) . '/vendor/autoload.php',
            $CFG->dirroot . '/vendor/autoload.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once($path);
                $loaded = true;
                return;
            }
        }

        throw new \moodle_exception('Composer autoload not found.');
    }

    private static function is_image(string $mimetype, string $ext): bool {
        return in_array($mimetype, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/bmp',
            'image/tiff',
        ], true) || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff'], true);
    }

    private static function is_text_like(string $mimetype, string $ext): bool {
        return (
            strpos($mimetype, 'text/') === 0 ||
            in_array($mimetype, ['application/json', 'application/xml', 'application/xhtml+xml'], true) ||
            in_array($ext, ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm', 'yaml', 'yml', 'log'], true)
        );
    }

    private static function clean_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\R{3,}/u', "\n\n", $text);
        return trim((string)$text);
    }
}