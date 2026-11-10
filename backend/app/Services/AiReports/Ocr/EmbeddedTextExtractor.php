<?php

namespace App\Services\AiReports\Ocr;

/**
 * Best-effort, dependency-free text extraction for documents that embed their
 * text natively (DOCX) or store it in content streams (PDF).
 *
 * This is deliberately conservative: returning null signals to the pipeline
 * that a scanner (e.g. Google Vision) is required. No bundled libraries are
 * used, keeping the backend dependency set unchanged.
 */
final class EmbeddedTextExtractor
{
    /**
     * Extract text from a DOCX archive (word/document.xml).
     */
    public function fromDocx(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || $xml === '') {
            return null;
        }

        $text = preg_replace('/<\/w:p>/i', "\n", $xml);
        $text = preg_replace('/<[^>]+>/', '', (string) $text);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', (string) $text);
        $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);

        $text = trim((string) $text);

        return $text !== '' ? $text : null;
    }

    /**
     * Best-effort text extraction from a PDF content stream.
     */
    public function fromPdf(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $buffer = '';
        while (! feof($handle)) {
            $buffer .= (string) fread($handle, 65536);
        }
        fclose($handle);

        $texts = [];

        // Parenthesized text operators: (literal) Tj  and  [ (a) (b) ] TJ
        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*(?:Tj|TJ|TJ\s*)/i', $buffer, $matches)) {
            foreach ($matches[0] as $hit) {
                $inner = mb_substr($hit, 1, -1);

                if (preg_match('/\)\s*Tj$/i', $hit)) {
                    $inner = mb_substr((string) $hit, 1, -3);
                }

                $inner = preg_replace('/\\\\([nrt()\\\\])/', ' ', (string) $inner);
                $inner = preg_replace_callback(
                    '/\\\\([0-7]{1,3})/',
                    static function (array $m): string {
                        $code = (int) octdec($m[1]);

                        return $code > 31 && $code < 127 ? chr($code) : ' ';
                    },
                    (string) $inner,
                );

                $clean = trim(preg_replace('/\s+/', ' ', (string) $inner));

                if ($clean !== '' && mb_strlen($clean) > 1) {
                    $texts[] = $clean;
                }
            }
        }

        if ($texts === []) {
            return null;
        }

        $text = trim(implode(' ', $texts));

        return $text !== '' ? $text : null;
    }
}
