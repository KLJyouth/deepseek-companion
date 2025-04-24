<?php
namespace Database\Seeders;

use Libs\DatabaseHelper;
use Libs\CryptoHelper;

class InitialUsers extends \Database\Seeder {
    public function run(DatabaseHelper $db): void {
        $crypto = CryptoHelper::getInstance();
        
        // 基础用户数据
        $users = [
            [
                'username' => 'admin',
                'password_hash' => $crypto->hashPassword('admin123'),
                'email' => 'admin@example.com',
                'is_admin' => true
            ],
            [
                'username' => 'user1',
                'password_hash' => $crypto->hashPassword('password1'),
                'email' => 'user1@example.com',
                'is_admin' => false
            ],
            [
                'username' => 'user2',
                'password_hash' => $crypto->hashPassword('password2'),
                'email' => 'user2@example.com',
                'is_admin' => false
            ]
        ];
        
        foreach ($users as $user) {
            $db->insert('users', $user);
        }
        
        // 开发环境额外测试用户
        if ($this->environment === 'development') {
            for ($i = 3; $i <= 5; $i++) {
                $db->insert('users', [
                    'username' => 'devuser'.$i,
                    'password_hash' => $crypto->hashPassword('devpass'.$i),
                    'email' => 'devuser'.$i.'@example.com',
                    'is_admin' => false
                ]);
            }
        }
    }
}