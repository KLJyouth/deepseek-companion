<?php
namespace Controllers;

use Libs\DatabaseHelper;
use Libs\CryptoHelper;
use Middlewares\AuthMiddleware;

abstract class BaseController {
    protected $db;
    protected $crypto;
    protected $auth;
    
    public function __construct() {
        $this->db = DatabaseHelper::getInstance();
        $this->crypto = CryptoHelper::getInstance();
        $this->auth = new AuthMiddleware($this->db, $this->crypto);
    }
    
    protected function jsonResponse(array $data, int $status = 200): void {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
    }
    
    protected function render(string $view, array $data = []): void {
        extract($data);
        require __DIR__ . "/../views/{$view}.php";
    }
    
    protected function redirect(string $url): void {
        header("Location: {$url}");
        exit;
    }
    
    protected function setFlashMessage(string $key, string $message): void {
        $_SESSION['flash'][$key] = $message;
    }
    
    protected function getFlashMessage(string $key): ?string {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    
    protected function validateCsrfToken(): void {
        $token = $_POST['_csrf'] ?? '';
        if (!$this->crypto->validateCsrfToken($token)) {
            throw new \Libs\Exception\SecurityException('无效的CSRF令牌');
        }
    }
}