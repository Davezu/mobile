<?php

defined("APP_ACCESS") or die("Direct access not allowed");

/**
 * Response Helper Class
 * Handles JSON responses for API
 */
class Response
{
    /**
     * Send JSON response
     */
    public static function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send success response
     */
    public static function success($message, $data = null, $statusCode = 200)
    {
        $response = [
            'success' => true,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send error response
     */
    public static function error($message, $errors = null, $statusCode = 400)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send validation error response
     */
    public static function validationError($errors, $message = 'Validation failed')
    {
        self::error($message, $errors, 422);
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access')
    {
        self::error($message, null, 401);
    }

    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Forbidden')
    {
        self::error($message, null, 403);
    }

    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found')
    {
        self::error($message, null, 404);
    }

    /**
     * Send server error response
     */
    public static function serverError($message = 'Internal server error')
    {
        self::error($message, null, 500);
    }
}

?>

