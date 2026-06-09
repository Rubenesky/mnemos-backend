<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ZipArchive;

class EmergencyKitService
{
    /**
     * Build a ZIP archive of emergency kit assets (max 50) and return the temp file path.
     * Each asset is streamed to a temp file so large binaries never fully load into PHP RAM.
     * Caller is responsible for deleting the returned ZIP file after streaming.
     *
     * @throws \RuntimeException if the ZIP archive cannot be created
     */
    public function build(): string
    {
        $assets  = Asset::where('is_emergency_kit', true)
                        ->with('metadata')
                        ->take(50)
                        ->get();

        $tmpPath   = sys_get_temp_dir() . '/mnemos-kit-' . Str::uuid() . '.zip';
        $tmpAssets = [];

        $zip    = new ZipArchive();
        $result = $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \RuntimeException("Failed to create emergency kit ZIP (ZipArchive error: {$result})");
        }

        try {
            foreach ($assets as $asset) {
                $url = $asset->cloudinary_url ?: (str_starts_with($asset->path ?? '', 'http') ? $asset->path : null);

                if ($url) {
                    try {
                        $tmpAsset    = tempnam(sys_get_temp_dir(), 'mnemos_asset_');
                        $tmpAssets[] = $tmpAsset;

                        // Stream response directly to disk — avoids loading the full body into PHP memory
                        $response = Http::timeout(30)->sink($tmpAsset)->get($url);
                        if ($response->successful()) {
                            $ext      = pathinfo($asset->original_name, PATHINFO_EXTENSION);
                            $safeName = $asset->metadata?->title
                                ? Str::slug($asset->metadata->title) . '.' . $ext
                                : $asset->original_name;
                            $zip->addFile($tmpAsset, $safeName);
                        }
                    } catch (\Throwable $e) {
                        // Skip unreachable files — don't fail the whole kit
                    }
                }
            }

            // Always include a manifest
            $manifest = "id,filename,title,url,mime_type\n";
            foreach ($assets as $asset) {
                $manifest .= implode(',', [
                    '"' . str_replace('"', '""', (string) $asset->id) . '"',
                    '"' . str_replace('"', '""', $asset->original_name) . '"',
                    '"' . str_replace('"', '""', $asset->metadata?->title ?? '') . '"',
                    '"' . str_replace('"', '""', $asset->cloudinary_url ?? '') . '"',
                    '"' . str_replace('"', '""', $asset->mime_type) . '"',
                ]) . "\n";
            }
            $zip->addFromString('manifest.csv', $manifest);

            // close() reads addFile() entries from disk — must happen before temp file cleanup
            $zip->close();
        } finally {
            foreach ($tmpAssets as $tmpFile) {
                @unlink($tmpFile);
            }
        }

        return $tmpPath;
    }
}
