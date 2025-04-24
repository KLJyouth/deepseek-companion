<?php
namespace Libs\Exception;

class SecurityException extends \RuntimeException {
    protected $code = 403;
    
    public function __construct(string $message = "Security violation", int $code = 403, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}