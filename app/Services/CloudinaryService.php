<?php

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected bool $configured = false;

    public function __construct()
    {
        $cloudinaryUrl = env('CLOUDINARY_URL');
        $url = is_string($cloudinaryUrl) ? $cloudinaryUrl : '';
        $this->configured = $url !== '' && str_starts_with($url, 'cloudinary://');

        if (!$this->configured) {
            Log::warning('Cloudinary not configured. CLOUDINARY_URL: ' . ($url !== '' ? 'set but invalid' : 'not set'));
        }
    }

    /**
     * Check if Cloudinary is configured
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Upload a file to Cloudinary using the official SDK (uploadApi()->upload).
     */
    public function upload(UploadedFile $file, string $folder = 'uploads', array $options = []): array
    {
        if (!$this->configured) {
            Log::error('Cloudinary upload attempted but not configured');
            return [
                'success' => false,
                'error' => 'Cloudinary is not configured',
            ];
        }

        $path = $file->getRealPath();
        if ($path === false || $path === '') {
            Log::error('Cloudinary upload: invalid file path');
            return [
                'success' => false,
                'error' => 'Invalid file or temporary file not available',
            ];
        }

        try {
            $uploadOptions = array_merge([
                'folder' => 'brems/' . $folder,
                'resource_type' => 'auto',
                'use_filename' => true,
                'unique_filename' => true,
            ], $options);

            Log::info('Cloudinary upload starting', [
                'folder' => $uploadOptions['folder'],
                'file' => $file->getClientOriginalName(),
            ]);

            $result = Cloudinary::uploadApi()->upload($path, $uploadOptions);

            // ApiResponse extends ArrayObject; upload returns public_id, secure_url, etc.
            $publicId = $result['public_id'] ?? null;
            $secureUrl = $result['secure_url'] ?? null;

            if (!$publicId) {
                Log::error('Cloudinary upload: no public_id in response');
                return [
                    'success' => false,
                    'error' => 'Invalid response from Cloudinary',
                ];
            }

            Log::info('Cloudinary upload success', ['public_id' => $publicId]);

            return [
                'success' => true,
                'public_id' => $publicId,
                'url' => $secureUrl ?: $this->getUrl($publicId, $options['resource_type'] ?? 'image'),
                'path' => $publicId,
            ];
        } catch (\Throwable $e) {
            Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload an image (resource_type image; no transformation in upload).
     */
    public function uploadImage(UploadedFile $file, string $folder = 'images'): array
    {
        return $this->upload($file, $folder, [
            'resource_type' => 'image',
        ]);
    }

    /**
     * Upload a document (PDF, etc.)
     */
    public function uploadDocument(UploadedFile $file, string $folder = 'documents'): array
    {
        return $this->upload($file, $folder, [
            'resource_type' => 'raw',
        ]);
    }

    /**
     * Delete a file from Cloudinary (uploadApi()->destroy).
     */
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        if (!$this->configured || $publicId === '') {
            return false;
        }

        try {
            // SDK whitelist for destroy is ['type', 'invalidate']; Cloudinary API expects resource_type, SDK uses 'type'.
            Cloudinary::uploadApi()->destroy($publicId, [
                'type' => $resourceType,
            ]);
            Log::info('Cloudinary delete success', ['public_id' => $publicId]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Cloudinary delete failed', [
                'error' => $e->getMessage(),
                'public_id' => $publicId,
            ]);
            return false;
        }
    }

    /**
     * Get URL for a public_id (image() or raw() + toUrl()).
     */
    public function getUrl(?string $publicId, string $resourceType = 'image'): ?string
    {
        if (!$publicId) {
            return null;
        }

        if (str_starts_with($publicId, 'http')) {
            return $publicId;
        }

        if (!$this->configured) {
            return null;
        }

        try {
            if ($resourceType === 'raw') {
                return (string) Cloudinary::raw($publicId)->toUrl();
            }
            return (string) Cloudinary::image($publicId)->toUrl();
        } catch (\Throwable $e) {
            Log::error('Cloudinary getUrl failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Determine resource type from file path (extension or brems/ prefix).
     */
    public function getResourceType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '') {
            return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : 'raw';
        }
        return str_contains($path, 'brems/') ? 'image' : 'raw';
    }

    /**
     * Check if path is a Cloudinary public_id (vs local path).
     */
    public function isCloudinaryPath(?string $path): bool
    {
        if (!$path) {
            return false;
        }
        return str_contains($path, 'brems/') || str_starts_with($path, 'http');
    }
}