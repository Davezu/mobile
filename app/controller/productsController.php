<?php

defined("APP_ACCESS") or die("Direct access not allowed");

require_once __DIR__ . '/../models/product.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/authController.php';

/**
 * Products Controller
 * Handles product operations
 */
class ProductsController
{
    private $productModel;

    public function __construct()
    {
        $this->productModel = new Product();
    }

    /**
     * Verify admin access
     * Returns user data if authenticated as admin, otherwise sends error response and exits
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
     * Get all products
     * GET /api/products
     */
    public function index()
    {
        $products = $this->productModel->getAll();
        Response::success('Products retrieved successfully', $products);
    }

    /**
     * Get product by ID
     * GET /api/products/{id}
     */
    public function show($id)
    {
        $product = $this->productModel->getById($id);
        
        if (!$product) {
            Response::notFound('Product not found');
        }
        
        Response::success('Product retrieved successfully', $product);
    }

    /**
     * Get products by category
     * GET /api/products/category/{category}
     */
    public function byCategory($category)
    {
        $products = $this->productModel->getByCategory($category);
        Response::success('Products retrieved successfully', $products);
    }

    /**
     * Search products
     * GET /api/products/search?q={query}
     */
    public function search()
    {
        $query = $_GET['q'] ?? '';
        
        if (empty($query)) {
            Response::error('Search query is required');
        }
        
        $products = $this->productModel->search($query);
        Response::success('Search results', $products);
    }

    /**
     * Create product (Admin only)
     * POST /api/admin/products
     */
    public function create()
    {
        // Verify admin access
        $this->requireAdmin();
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid JSON data');
        }

        // Validate input
        $validator = new Validator($input);
        $validator
            ->required('name')
            ->minLength('name', 3)
            ->maxLength('name', 255)
            
            ->required('description')
            ->minLength('description', 10)
            
            ->required('price')
            ->numeric('price')
            
            ->required('stock')
            ->numeric('stock');

        // Check if validation failed
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Create product
        try {
            $data = [
                'name' => $input['name'],
                'description' => $input['description'],
                'price' => $input['price'],
                'stock' => $input['stock'],
                'category' => $input['category'] ?? null,
                'image_url' => $input['image_url'] ?? null,
            ];

            $product = $this->productModel->create($data);

            if ($product) {
                Response::success('Product created successfully', $product, 201);
            } else {
                Response::error('Failed to create product');
            }
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Update product (Admin only)
     * PUT /api/admin/products/{id}
     */
    public function update($id)
    {
        // Verify admin access
        $this->requireAdmin();
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('Invalid JSON data');
        }

        // Check if product exists
        $product = $this->productModel->getById($id);
        if (!$product) {
            Response::notFound('Product not found');
        }

        // Update product
        try {
            $data = [];
            if (isset($input['name'])) $data['name'] = $input['name'];
            if (isset($input['description'])) $data['description'] = $input['description'];
            if (isset($input['price'])) $data['price'] = $input['price'];
            if (isset($input['stock'])) $data['stock'] = $input['stock'];
            if (isset($input['category'])) $data['category'] = $input['category'];
            if (isset($input['image_url'])) $data['image_url'] = $input['image_url'];

            $updated = $this->productModel->update($id, $data);

            if ($updated) {
                Response::success('Product updated successfully', $updated);
            } else {
                Response::error('Failed to update product');
            }
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }

    /**
     * Delete product (Admin only)
     * DELETE /api/admin/products/{id}
     */
    public function delete($id)
    {
        // Verify admin access
        $this->requireAdmin();
        
        // Check if product exists
        $product = $this->productModel->getById($id);
        if (!$product) {
            Response::notFound('Product not found');
        }

        try {
            if ($this->productModel->delete($id)) {
                Response::success('Product deleted successfully');
            } else {
                Response::error('Failed to delete product');
            }
        } catch (Exception $e) {
            Response::serverError($e->getMessage());
        }
    }
}

