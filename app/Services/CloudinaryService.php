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
        $this->configured = !empty($cloudinaryUrl) && str_starts_with($cloudinaryUrl, 'cloudinary://');
        
        if (!$this->configured) {
            Log::warning('Cloudinary not configured. CLOUDINARY_URL: ' . ($cloudinaryUrl ? 'set but invalid' : 'not set'));
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
     * Upload a file to Cloudinary
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

            $result = Cloudinary::upload($file->getRealPath(), $uploadOptions);

            Log::info('Cloudinary upload success', [
                'public_id' => $result->getPublicId(),
            ]);

            return [
                'success' => true,
                'public_id' => $result->getPublicId(),
                'url' => $result->getSecurePath(),
                'path' => $result->getPublicId(),
            ];
        } catch (\Exception $e) {
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
     * Upload an image
     */
    public function uploadImage(UploadedFile $file, string $folder = 'images'): array
    {
        return $this->upload($file, $folder, [
            'resource_type' => 'image',
            'transformation' => [
                'quality' => 'auto',
                'fetch_format' => 'auto',
            ],
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
     * Delete a file from Cloudinary
     */
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        if (!$this->configured || empty($publicId)) {
            return false;
        }

        try {
            Cloudinary::destroy($publicId, ['resource_type' => $resourceType]);
            Log::info('Cloudinary delete success', ['public_id' => $publicId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Cloudinary delete failed', [
                'error' => $e->getMessage(),
                'public_id' => $publicId,
            ]);
            return false;
        }
    }

    /**
     * Get URL for a public_id
     */
    public function getUrl(?string $publicId, string $resourceType = 'image'): ?string
    {
        if (!$publicId) {
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($publicId, 'http')) {
            return $publicId;
        }

        if (!$this->configured) {
            return null;
        }

        try {
            return Cloudinary::getUrl($publicId, [
                'secure' => true,
                'resource_type' => $resourceType,
            ]);
        } catch (\Exception $e) {
            Log::error('Cloudinary getUrl failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Determine resource type from file path
     */
    public function getResourceType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : 'raw';
    }
}