<?php
namespace Models;

use Libs\DatabaseHelper;
use Libs\CryptoHelper;
use Libs\Exception\SecurityException;

class User extends BaseModel {
    protected $table = 'users';
    
    public $id;
    public $username;
    public $password_hash;
    public $email;
    public $is_admin = false;
    public $is_locked = false;
    public $created_at;
    public $updated_at;
    public $last_login_at;
    public $failed_login_attempts = 0;
    
    public static function findByUsername(string $username): ?self {
        $users = self::where('username', $username);
        return $users[0] ?? null;
    }
    
    public static function create(array $data, CryptoHelper $crypto): self {
        self::validateUserData($data);
        
        $user = new self();
        $user->username = $data['username'];
        $user->password_hash = $crypto->hashPassword($data['password']);
        $user->email = $data['email'] ?? null;
        $user->is_admin = $data['is_admin'] ?? false;
        
        if (!$user->save()) {
            throw new SecurityException('创建用户失败');
        }
        
        return $user;
    }
    
    public static function validateUserData(array $data): void {
        if (empty($data['username'])) {
            throw new SecurityException('用户名不能为空');
        }
        
        if (empty($data['password'])) {
            throw new SecurityException('密码不能为空');
        }
        
        if (strlen($data['password']) < 8) {
            throw new SecurityException('密码长度不能少于8个字符');
        }
    }
    
    public function verifyPassword(string $password, CryptoHelper $crypto): bool {
        return $crypto->verifyPassword($password, $this->password_hash);
    }
    
    public function updatePassword(string $newPassword, CryptoHelper $crypto): void {
        $this->password_hash = $crypto->hashPassword($newPassword);
        $this->save();
    }
    
    public function recordLoginSuccess(): void {
        $this->last_login_at = date('Y-m-d H:i:s');
        $this->failed_login_attempts = 0;
        $this->save();
    }
    
    public function recordLoginFailure(): void {
        $this->failed_login_attempts++;
        
        if ($this->failed_login_attempts >= 5) {
            $this->is_locked = true;
        }
        
        $this->save();
    }
    
    public function unlock(): void {
        $this->is_locked = false;
        $this->failed_login_attempts = 0;
        $this->save();
    }
    
    public function contracts(): array {
        return Contract::where('created_by', $this->id);
    }
    
    public function signedContracts(): array {
        $signatures = ContractSignature::where('user_id', $this->id);
        return array_map(function($signature) {
            return $signature->contract();
        }, $signatures);
    }
}