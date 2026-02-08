<?php

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    /**
     * Upload a file to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param array $options
     * @return array
     */
    public function upload(UploadedFile $file, string $folder = 'uploads', array $options = []): array
    {
        try {
            $defaultOptions = [
                'folder' => 'brems/' . $folder,
                'resource_type' => 'auto', // Handles images, PDFs, etc.
                'use_filename' => true,
                'unique_filename' => true,
            ];

            $uploadOptions = array_merge($defaultOptions, $options);

            $result = Cloudinary::upload($file->getRealPath(), $uploadOptions);

            return [
                'success' => true,
                'public_id' => $result->getPublicId(),
                'url' => $result->getSecurePath(),
                'path' => $result->getPublicId(), // Store public_id as path in database
                'format' => $result->getExtension(),
                'size' => $result->getSize(),
                'resource_type' => $result->getFileType(),
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload error: ' . $e->getMessage(), [
                'file' => $file->getClientOriginalName(),
                'folder' => $folder,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload an image with transformations
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param array $transformations
     * @return array
     */
    public function uploadImage(UploadedFile $file, string $folder = 'images', array $transformations = []): array
    {
        $defaultTransformations = [
            'quality' => 'auto',
            'fetch_format' => 'auto',
        ];

        $options = [
            'transformation' => array_merge($defaultTransformations, $transformations),
        ];

        return $this->upload($file, $folder, $options);
    }

    /**
     * Upload a PDF document
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return array
     */
    public function uploadDocument(UploadedFile $file, string $folder = 'documents'): array
    {
        return $this->upload($file, $folder, [
            'resource_type' => 'raw', // For non-image files like PDFs
        ]);
    }

    /**
     * Delete a file from Cloudinary
     *
     * @param string $publicId
     * @param string $resourceType
     * @return bool
     */
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        try {
            Cloudinary::destroy($publicId, [
                'resource_type' => $resourceType,
            ]);
            Log::info('Cloudinary file deleted: ' . $publicId);
            return true;
        } catch (\Exception $e) {
            Log::error('Cloudinary delete error: ' . $e->getMessage(), [
                'public_id' => $publicId,
            ]);
            return false;
        }
    }

    /**
     * Delete multiple files
     *
     * @param array $publicIds
     * @param string $resourceType
     * @return bool
     */
    public function deleteMultiple(array $publicIds, string $resourceType = 'image'): bool
    {
        try {
            foreach ($publicIds as $publicId) {
                $this->delete($publicId, $resourceType);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Cloudinary bulk delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get URL for a public_id
     *
     * @param string|null $publicId
     * @param string $resourceType
     * @param array $transformations
     * @return string|null
     */
    public function getUrl(?string $publicId, string $resourceType = 'image', array $transformations = []): ?string
    {
        if (!$publicId) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($publicId, 'http://') || str_starts_with($publicId, 'https://')) {
            return $publicId;
        }

        try {
            if ($resourceType === 'raw') {
                // For PDFs and other documents
                return Cloudinary::getUrl($publicId, [
                    'resource_type' => 'raw',
                    'secure' => true,
                ]);
            }

            // For images with optional transformations
            $options = array_merge([
                'secure' => true,
                'resource_type' => $resourceType,
            ], $transformations);

            return Cloudinary::getUrl($publicId, $options);
        } catch (\Exception $e) {
            Log::error('Cloudinary getUrl error: ' . $e->getMessage(), [
                'public_id' => $publicId,
            ]);
            return null;
        }
    }

    /**
     * Get thumbnail URL for an image
     *
     * @param string|null $publicId
     * @param int $width
     * @param int $height
     * @return string|null
     */
    public function getThumbnailUrl(?string $publicId, int $width = 150, int $height = 150): ?string
    {
        if (!$publicId) {
            return null;
        }

        try {
            return Cloudinary::getUrl($publicId, [
                'secure' => true,
                'transformation' => [
                    'width' => $width,
                    'height' => $height,
                    'crop' => 'fill',
                    'quality' => 'auto',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->getUrl($publicId);
        }
    }

    /**
     * Check if a path is a Cloudinary public_id (vs local path)
     *
     * @param string|null $path
     * @return bool
     */
    public function isCloudinaryPath(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        // Cloudinary public_ids typically contain 'brems/' prefix we set
        return str_contains($path, 'brems/') || str_starts_with($path, 'http');
    }

    /**
     * Determine resource type from file extension
     *
     * @param string $path
     * @return string
     */
    public function getResourceType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Cloudinary public_id has no extension; default to image (profile pics, most certs)
        if ($extension === '') {
            return str_contains($path, 'brems/') ? 'image' : 'raw';
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        if (in_array($extension, $imageExtensions)) {
            return 'image';
        }

        return 'raw'; // PDFs, documents, etc.
    }
}