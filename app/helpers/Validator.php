<?php

namespace App\Helpers;

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
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = $message ?? ucfirst($field) . ' is required';
        }
        return $this;
    }

    /**
     * Validate email format
     */
    public function email($field, $message = null)
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? 'Invalid email format';
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function min($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must be at least {$length} characters";
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function max($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must not exceed {$length} characters";
        }
        return $this;
    }

    /**
     * Validate field matches another field
     */
    public function matches($field, $matchField, $message = null)
    {
        if (isset($this->data[$field]) && isset($this->data[$matchField]) && $this->data[$field] !== $this->data[$matchField]) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must match ' . ucfirst($matchField);
        }
        return $this;
    }

    /**
     * Validate phone number (Indian format)
     */
    public function phone($field, $message = null)
    {
        if (isset($this->data[$field]) && !preg_match('/^[6-9]\d{9}$/', $this->data[$field])) {
            $this->errors[$field] = $message ?? 'Invalid phone number';
        }
        return $this;
    }

    /**
     * Custom validation
     */
    public function custom($field, $callback, $message = null)
    {
        if (isset($this->data[$field]) && !$callback($this->data[$field])) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' is invalid';
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
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get first error
     */
    public function getFirstError()
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * Get error for specific field
     */
    public function getError($field)
    {
        return $this->errors[$field] ?? null;
    }
}