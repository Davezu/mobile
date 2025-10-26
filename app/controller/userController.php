<?php
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/authController.php';

class UserController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Verify admin access
     */
    private function requireAdmin()
    {
        $user = verifyToken();

        if (!$user) {
            Response::forbidden('Please login to continue');
        }

        if ($user['role'] !== 'admin') {
            Response::forbidden('Admin access required');
        }

        return $user;
    }

    /**
     * Get all users (admin only)
     * GET /users
     */
    public function getAllUsers()
    {
        $this->requireAdmin();

        try {
            $users = $this->userModel->getAll();
            Response::success('Users retrieved successfully', $users);
        } catch (Exception $e) {
            Response::error('Failed to fetch users: ' . $e->getMessage());
        }
    }

    /**
     * Get user by ID (admin only)
     * GET /users/{id}
     */
    public function getUserById($id)
    {
        $this->requireAdmin();

        try {
            $user = $this->userModel->findById($id);
            if ($user) {
                Response::success('User found', $user);
            } else {
                Response::notFound('User not found');
            }
        } catch (Exception $e) {
            Response::error('Failed to fetch user: ' . $e->getMessage());
        }
    }

    /**
     * Update user status (admin only)
     * PUT /users/{id}/status
     */
    public function updateUserStatus($id)
    {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['is_active'])) {
            Response::badRequest('is_active field is required');
        }

        try {
            $success = $this->userModel->updateStatus($id, $data['is_active']);
            if ($success) {
                $user = $this->userModel->findById($id);
                Response::success('User status updated successfully', $user);
            } else {
                Response::error('Failed to update user status');
            }
        } catch (Exception $e) {
            Response::error('Failed to update user status: ' . $e->getMessage());
        }
    }

    /**
     * Update user role (admin only)
     * PUT /users/{id}/role
     */
    public function updateUserRole($id)
    {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['role'])) {
            Response::badRequest('role field is required');
        }

        if (!in_array($data['role'], ['admin', 'customer'])) {
            Response::badRequest('Invalid role. Must be admin or customer');
        }

        try {
            $success = $this->userModel->updateRole($id, $data['role']);
            if ($success) {
                $user = $this->userModel->findById($id);
                Response::success('User role updated successfully', $user);
            } else {
                Response::error('Failed to update user role');
            }
        } catch (Exception $e) {
            Response::error('Failed to update user role: ' . $e->getMessage());
        }
    }

    /**
     * Delete user (admin only)
     * DELETE /users/{id}
     */
    public function deleteUser($id)
    {
        $admin = $this->requireAdmin();

        // Prevent self-deletion
        if ($admin['id'] == $id) {
            Response::badRequest('You cannot delete your own account');
        }

        try {
            $user = $this->userModel->findById($id);
            if (!$user) {
                Response::notFound('User not found');
            }

            // Prevent deleting other admins
            if ($user['role'] === 'admin') {
                Response::badRequest('Cannot delete admin users');
            }

            $success = $this->userModel->delete($id);
            if ($success) {
                Response::success('User deleted successfully');
            } else {
                Response::error('Failed to delete user');
            }
        } catch (Exception $e) {
            Response::error('Failed to delete user: ' . $e->getMessage());
        }
    }
}

