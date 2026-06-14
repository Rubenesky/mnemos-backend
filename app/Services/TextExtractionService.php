<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extracts plain text from PDF and Word documents for AI processing.
 */
class TextExtractionService
{
    private const MAX_CHARS = 4000;

    private const SUPPORTED_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function isSupported(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_TYPES);
    }

    public function extract(string $url, string $mimeType): string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if ($response->failed()) {
                Log::warning('TextExtractionService: failed to download file', [
                    'status' => $response->status(),
                ]);

                return '';
            }

            $ext = $this->extensionFor($mimeType);
            $tmpFile = sys_get_temp_dir().'/mnemos_'.uniqid().$ext;
            file_put_contents($tmpFile, $response->body());

            try {
                $text = match ($mimeType) {
                    'application/pdf' => $this->extractPdf($tmpFile),
                    default => $this->extractWord($tmpFile, $mimeType),
                };
            } finally {
                @unlink($tmpFile);
            }

            return mb_substr(trim($text), 0, self::MAX_CHARS);

        } catch (\Exception $e) {
            Log::error('TextExtractionService::extract exception', [
                'error' => $e->getMessage(),
                'mime' => $mimeType,
            ]);

            return '';
        }
    }

    private function extractPdf(string $path): string
    {
        $parser = new \Smalot\PdfParser\Parser;
        $pdf = $parser->parseFile($path);

        return $pdf->getText();
    }

    private function extractWord(string $path, string $mimeType): string
    {
        $readerType = $mimeType === 'application/msword' ? 'MsDoc' : 'Word2007';
        $reader = \PhpOffice\PhpWord\IOFactory::createReader($readerType);
        $phpWord = $reader->load($path);

        $lines = [];
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $line = $this->elementText($element);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", $lines);
    }

    private function elementText(object $element): string
    {
        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            return $element->getText();
        }

        if (method_exists($element, 'getElements')) {
            $parts = [];
            foreach ($element->getElements() as $child) {
                $t = $this->elementText($child);
                if ($t !== '') {
                    $parts[] = $t;
                }
            }

            return implode(' ', $parts);
        }

        if (method_exists($element, 'getText')) {
            return (string) $element->getText();
        }

        return '';
    }

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => '.pdf',
            'application/msword' => '.doc',
            default => '.docx',
        };
    }
}
