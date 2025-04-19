<?php
namespace Libs\Exception;

class SecurityException extends \Exception {
    private $riskLevel;

    public function __construct(string $message = "", int $riskLevel = 0, \Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
        $this->riskLevel = $riskLevel;
    }

    public function getRiskLevel(): int {
        return $this->riskLevel;
    }
}