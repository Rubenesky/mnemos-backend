<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;

/**
 * Wraps the Cloudinary SDK to upload and delete media assets in the configured cloud storage folder.
 */
class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME', config('cloudinary.cloud_name'));
        $apiKey = env('CLOUDINARY_API_KEY', config('cloudinary.api_key'));
        $apiSecret = env('CLOUDINARY_API_SECRET', config('cloudinary.api_secret'));

        Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->cloudinary = new Cloudinary;
    }

    public function upload(UploadedFile $file): array
    {
        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => 'mnemos',
                'resource_type' => 'auto',
                'use_filename' => false,
                'unique_filename' => true,
            ]
        );

        return [
            'public_id' => $result['public_id'],
            'url' => $result['secure_url'],
            'format' => $result['format'],
        ];
    }

    public function delete(string $publicId): void
    {
        $this->cloudinary->uploadApi()->destroy($publicId);
    }

    /**
     * Performs a lightweight Admin API ping to verify Cloudinary connectivity.
     *
     * @return bool True if the service responds with status "ok".
     */
    public function ping(): bool
    {
        try {
            $result = $this->cloudinary->adminApi()->ping();

            return ($result['status'] ?? '') === 'ok';
        } catch (\Exception) {
            return false;
        }
    }
}
