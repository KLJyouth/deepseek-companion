<?php
namespace Controllers;

use Libs\DatabaseHelper;
use Libs\CryptoHelper;
use Middlewares\AuthMiddleware;
use Models\User;
use Libs\Exception\SecurityException;

class LoginController extends BaseController {
    private $deviceService;
    
    public function __construct() {
        parent::__construct();
        $this->deviceService = new \Services\DeviceManagementService($this->db);
    }
    
    public function loginAction(): void {
        try {
            $this->validateCsrfToken();
            $this->validateLoginInput();
            
            $username = $_POST['username'];
            $password = $_POST['password'];
            $remember = isset($_POST['remember']);
            
            $user = $this->authenticateUser($username, $password);
            $this->createSession($user, $remember);
            
            $this->logLoginSuccess($user->id);
            $this->redirect('/dashboard');
            
        } catch (SecurityException $e) {
            $this->logLoginFailure($username ?? '', $e->getMessage());
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/login');
        }
    }
    
    private function validateLoginInput(): void {
        if (empty($_POST['username']) || empty($_POST['password'])) {
            throw new SecurityException('用户名和密码不能为空');
        }
    }
    
    private function authenticateUser(string $username, string $password): User {
        $user = User::where('username', $username)[0] ?? null;
        
        if (!$user || !$this->crypto->verifyPassword($password, $user->password_hash)) {
            throw new SecurityException('用户名或密码错误');
        }
        
        if ($user->is_locked) {
            throw new SecurityException('账户已被锁定，请联系管理员');
        }
        
        return $user;
    }
    
    private function createSession(User $user, bool $remember): void {
        $this->auth->secureSession();
        
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['is_admin'] = $user->is_admin;
        
        if ($remember) {
            $token = $this->generateRememberToken($user->id);
            $this->setRememberCookie($token);
        }
        
        $this->deviceService->recordDevice($user->id);
    }
    
    private function generateRememberToken(int $userId): string {
        $token = $this->crypto->generateToken();
        $expires = time() + 60 * 60 * 24 * 30; // 30天
        
        $this->db->insert('remember_tokens', [
            'user_id' => $userId,
            'token' => $this->crypto->customEncode($token),
            'expires_at' => date('Y-m-d H:i:s', $expires)
        ]);
        
        return $token;
    }
    
    private function setRememberCookie(string $token): void {
        setcookie(
            'remember_token',
            $token,
            time() + 60 * 60 * 24 * 30,
            '/',
            '',
            true,
            true
        );
    }
    
    private function logLoginSuccess(int $userId): void {
        $this->db->logAudit('login_success', $userId, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    private function logLoginFailure(string $username, string $error): void {
        $this->db->logAudit('login_failed', null, [
            'username' => $username,
            'error' => $error,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    public function logoutAction(): void {
        $this->auth->destroySession();
        $this->redirect('/login');
    }
}