<?php

defined("APP_ACCESS") or die("Direct access not allowed");

/**
 * Firebase Storage Service
 * Handles image uploads to Firebase Storage
 */
class FirebaseStorageService
{
    private $storage;
    private $bucket;
    private $bucketName;

    public function __construct()
    {
        try {
            require_once __DIR__ . '/../../config/config.php';
            
            // Get Firebase configuration from environment
            $serviceAccountPath = env('FIREBASE_SERVICE_ACCOUNT_PATH', __DIR__ . '/../../config/firebase-service-account.json');
            $this->bucketName = env('FIREBASE_STORAGE_BUCKET', '');
            
            if (empty($this->bucketName)) {
                throw new Exception('FIREBASE_STORAGE_BUCKET not configured');
            }
            
            if (!file_exists($serviceAccountPath)) {
                throw new Exception('Firebase service account file not found at: ' . $serviceAccountPath);
            }
            
            // Initialize Firebase
            $factory = (new \Kreait\Firebase\Factory())
                ->withServiceAccount($serviceAccountPath);
            
            $this->storage = $factory->createStorage();
            $this->bucket = $this->storage->getBucket($this->bucketName);
            
        } catch (Exception $e) {
            error_log("Firebase Storage initialization error: " . $e->getMessage());
            throw new Exception("Failed to initialize Firebase Storage: " . $e->getMessage());
        }
    }

    /**
     * Upload image to Firebase Storage
     * 
     * @param string $filePath Local file path
     * @param string $destinationPath Path in Firebase Storage (e.g., 'products/image.jpg')
     * @return string Public URL of uploaded image
     */
    public function uploadImage($filePath, $destinationPath = null)
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('File not found: ' . $filePath);
            }
            
            // Generate destination path if not provided
            if (!$destinationPath) {
                $filename = basename($filePath);
                $destinationPath = 'products/' . uniqid() . '_' . time() . '_' . $filename;
            }
            
            // Ensure path starts with 'products/'
            if (strpos($destinationPath, 'products/') !== 0) {
                $destinationPath = 'products/' . $destinationPath;
            }
            
            // Upload file
            $object = $this->bucket->upload(
                fopen($filePath, 'r'),
                [
                    'name' => $destinationPath,
                    'metadata' => [
                        'contentType' => mime_content_type($filePath),
                        'cacheControl' => 'public, max-age=31536000',
                    ]
                ]
            );
            
            // Make the file publicly accessible
            $object->update(['acl' => []], ['predefinedAcl' => 'PUBLIC_READ']);
            
            // Get public URL
            $publicUrl = 'https://storage.googleapis.com/' . $this->bucketName . '/' . $destinationPath;
            
            return $publicUrl;
            
        } catch (Exception $e) {
            error_log("Firebase Storage upload error: " . $e->getMessage());
            throw new Exception("Failed to upload image to Firebase: " . $e->getMessage());
        }
    }

    /**
     * Upload image from binary data
     * 
     * @param string $imageData Binary image data
     * @param string $filename Original filename
     * @param string $mimeType MIME type (e.g., 'image/jpeg')
     * @return string Public URL of uploaded image
     */
    public function uploadImageData($imageData, $filename, $mimeType = 'image/jpeg')
    {
        try {
            // Generate unique filename
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $destinationPath = 'products/' . uniqid() . '_' . time() . '.' . $extension;
            
            // Create temporary file
            $tempFile = sys_get_temp_dir() . '/' . uniqid() . '_' . $filename;
            file_put_contents($tempFile, $imageData);
            
            try {
                // Upload to Firebase
                $object = $this->bucket->upload(
                    fopen($tempFile, 'r'),
                    [
                        'name' => $destinationPath,
                        'metadata' => [
                            'contentType' => $mimeType,
                            'cacheControl' => 'public, max-age=31536000',
                        ]
                    ]
                );
                
                // Make publicly accessible
                $object->update(['acl' => []], ['predefinedAcl' => 'PUBLIC_READ']);
                
                // Get public URL
                $publicUrl = 'https://storage.googleapis.com/' . $this->bucketName . '/' . $destinationPath;
                
                return $publicUrl;
            } finally {
                // Clean up temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
        } catch (Exception $e) {
            error_log("Firebase Storage upload error: " . $e->getMessage());
            throw new Exception("Failed to upload image to Firebase: " . $e->getMessage());
        }
    }

    /**
     * Delete image from Firebase Storage
     * 
     * @param string $imageUrl Full URL or path to image
     * @return bool Success status
     */
    public function deleteImage($imageUrl)
    {
        try {
            // Extract path from URL
            $path = $this->extractPathFromUrl($imageUrl);
            
            if (!$path) {
                throw new Exception('Invalid image URL');
            }
            
            // Delete object
            $object = $this->bucket->object($path);
            $object->delete();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Firebase Storage delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract storage path from URL
     * 
     * @param string $url Full Firebase Storage URL
     * @return string|null Storage path
     */
    private function extractPathFromUrl($url)
    {
        // Handle different URL formats
        // https://storage.googleapis.com/bucket-name/path/to/file.jpg
        // https://firebasestorage.googleapis.com/v0/b/bucket-name/o/path%2Fto%2Ffile.jpg?alt=media
        
        if (strpos($url, 'storage.googleapis.com') !== false) {
            // Direct storage URL
            $parts = parse_url($url);
            $path = ltrim($parts['path'], '/');
            // Remove bucket name from path
            $pathParts = explode('/', $path, 2);
            return isset($pathParts[1]) ? $pathParts[1] : $pathParts[0];
        } elseif (strpos($url, 'firebasestorage.googleapis.com') !== false) {
            // Firebase Storage API URL
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            if (isset($params['name'])) {
                return urldecode($params['name']);
            }
            // Try to extract from path
            $path = parse_url($url, PHP_URL_PATH);
            if (preg_match('/\/o\/(.+)$/', $path, $matches)) {
                return urldecode($matches[1]);
            }
        }
        
        // If URL format not recognized, assume it's already a path
        return $url;
    }

    /**
     * Check if Firebase Storage is configured
     * 
     * @return bool
     */
    public static function isConfigured()
    {
        try {
            $serviceAccountPath = env('FIREBASE_SERVICE_ACCOUNT_PATH', __DIR__ . '/../../config/firebase-service-account.json');
            $bucketName = env('FIREBASE_STORAGE_BUCKET', '');
            
            return !empty($bucketName) && file_exists($serviceAccountPath);
        } catch (Exception $e) {
            return false;
        }
    }
}

