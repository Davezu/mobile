<?php

defined("APP_ACCESS") or die("Direct access not allowed");

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/authController.php';

/**
 * Image Upload Controller
 * Handles image uploads for products
 * Images are stored locally and URLs are saved in the database
 */
class ImageController
{
    private $uploadDir = 'uploads/products/';
    private $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private $maxFileSize = 5 * 1024 * 1024; // 5MB

    public function __construct()
    {
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Verify admin access
     */
    private function requireAdmin()
    {
        $user = verifyToken();
        
        if (!$user) {
            Response::unauthorized('Authentication required');
        }
        
        if ($user['role'] !== 'admin') {
            Response::forbidden('Admin access required');
        }
        
        return $user;
    }

    /**
     * Upload product image
     * POST /api/admin/upload-image
     */
    public function uploadImage()
    {
        // Verify admin access
        $this->requireAdmin();

        // Check if file was uploaded
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            Response::error('No image file uploaded or upload error occurred');
        }

        $file = $_FILES['image'];
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            Response::error('File size too large. Maximum size is 5MB');
        }

        // Get file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($fileExtension, $this->allowedTypes)) {
            Response::error('Invalid file type. Allowed types: ' . implode(', ', $this->allowedTypes));
        }

        // Upload to local storage
        $filename = uniqid() . '_' . time() . '.' . $fileExtension;
        $filepath = $this->uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $baseUrl = $this->getBaseUrl();
            $imageUrl = $baseUrl . '/' . $filepath;
            
            Response::success('Image uploaded successfully', [
                'image_url' => $imageUrl,
                'filename' => $filename,
                'filepath' => $filepath
            ]);
        } else {
            Response::error('Failed to save uploaded file');
        }
    }

    /**
     * Upload image from URL
     * POST /api/admin/upload-image-url
     */
    public function uploadImageFromUrl()
    {
        // Verify admin access
        $this->requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['image_url'])) {
            Response::error('Image URL is required');
        }

        $imageUrl = $input['image_url'];
        
        // Validate URL
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            Response::error('Invalid URL format');
        }

        // Download image
        $imageData = @file_get_contents($imageUrl);
        
        if ($imageData === false) {
            Response::error('Failed to download image from URL');
        }

        // Get file info
        $fileInfo = @getimagesize($imageUrl);
        if ($fileInfo === false) {
            Response::error('Invalid image file');
        }

        // Determine file extension from MIME type
        $mimeType = $fileInfo['mime'];
        $extension = '';
        
        switch ($mimeType) {
            case 'image/jpeg':
                $extension = 'jpg';
                break;
            case 'image/png':
                $extension = 'png';
                break;
            case 'image/gif':
                $extension = 'gif';
                break;
            case 'image/webp':
                $extension = 'webp';
                break;
            default:
                Response::error('Unsupported image format');
        }

        // Upload to local storage
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;

        if (file_put_contents($filepath, $imageData)) {
            $baseUrl = $this->getBaseUrl();
            $imageUrl = $baseUrl . '/' . $filepath;
            
            Response::success('Image uploaded successfully', [
                'image_url' => $imageUrl,
                'filename' => $filename,
                'filepath' => $filepath
            ]);
        } else {
            Response::error('Failed to save image');
        }
    }

    /**
     * Delete image
     * DELETE /api/admin/delete-image
     */
    public function deleteImage()
    {
        // Verify admin access
        $this->requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['image_url'])) {
            Response::error('Image URL is required');
        }

        $imageUrl = $input['image_url'];
        
        // Delete from local storage
        $baseUrl = $this->getBaseUrl();
        $filepath = str_replace($baseUrl . '/', '', $imageUrl);
        
        // Security check - ensure file is in upload directory
        if (strpos($filepath, $this->uploadDir) !== 0) {
            Response::error('Invalid file path');
        }

        // Delete file
        if (file_exists($filepath) && unlink($filepath)) {
            Response::success('Image deleted successfully');
        } else {
            Response::error('Failed to delete image or file not found');
        }
    }

    /**
     * Get base URL for the application
     */
    private function getBaseUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . $scriptName;
    }

    /**
     * Get all uploaded images
     * GET /api/admin/images
     */
    public function getImages()
    {
        // Verify admin access
        $this->requireAdmin();

        $images = [];
        $baseUrl = $this->getBaseUrl();

        if (is_dir($this->uploadDir)) {
            $files = scandir($this->uploadDir);
            
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && !is_dir($this->uploadDir . $file)) {
                    $images[] = [
                        'filename' => $file,
                        'url' => $baseUrl . '/' . $this->uploadDir . $file,
                        'size' => filesize($this->uploadDir . $file),
                        'modified' => filemtime($this->uploadDir . $file)
                    ];
                }
            }
        }

        Response::success('Images retrieved successfully', $images);
    }
}
?>
