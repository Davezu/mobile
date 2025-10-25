<?php

defined("APP_ACCESS") or die("Direct access not allowed");

/**
 * Validator Class
 * Handles input validation
 */
class Validator
{
    private $errors = [];
    private $data = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Validate required field
     */
    public function required($field, $message = null)
    {
        if (!isset($this->data[$field]) || 
            (is_string($this->data[$field]) && trim($this->data[$field]) === '') ||
            (is_array($this->data[$field]) && empty($this->data[$field])) ||
            (is_null($this->data[$field]))) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' is required';
        }
        return $this;
    }

    /**
     * Validate email format
     */
    public function email($field, $message = null)
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?? 'Invalid email format';
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function minLength($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field][] = $message ?? ucfirst($field) . " must be at least $length characters";
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function maxLength($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field][] = $message ?? ucfirst($field) . " must not exceed $length characters";
        }
        return $this;
    }

    /**
     * Validate field matches another field
     */
    public function matches($field, $matchField, $message = null)
    {
        if (isset($this->data[$field]) && isset($this->data[$matchField])) {
            if ($this->data[$field] !== $this->data[$matchField]) {
                $this->errors[$field][] = $message ?? ucfirst($field) . ' does not match';
            }
        }
        return $this;
    }

    /**
     * Validate unique value in database
     */
    public function unique($field, $table, $column = null, $message = null)
    {
        if (!isset($this->data[$field])) {
            return $this;
        }

        $column = $column ?? $field;
        $db = Database::getInstance();
        
        $query = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
        $result = $db->fetch($query, [$this->data[$field]]);
        
        if ($result['count'] > 0) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' already exists';
        }
        
        return $this;
    }

    /**
     * Validate pattern (regex)
     */
    public function pattern($field, $pattern, $message = null)
    {
        if (isset($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' format is invalid';
        }
        return $this;
    }

    /**
     * Validate numeric value
     */
    public function numeric($field, $message = null)
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' must be a number';
        }
        return $this;
    }

    /**
     * Validate value is in array
     */
    public function in($field, $values, $message = null)
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' is not valid';
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes()
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails()
    {
        return !$this->passes();
    }

    /**
     * Get all errors
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function first($field)
    {
        return $this->errors[$field][0] ?? null;
    }
}

?>

