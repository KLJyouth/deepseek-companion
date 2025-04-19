<?php
namespace Libs;

/**
 * 权限策略验证器
 * 处理复杂的权限继承关系验证
 */
class PermissionPolicyValidator {
    /**
     * 递归检查权限继承链
     */
    public static function checkInheritanceChain(array $policy, string $currentRole, string $targetPermission): bool {
        $visited = [];
        return self::checkRecursive($policy, $currentRole, $targetPermission, $visited);
    }

    private static function checkRecursive(array $policy, string $currentRole, string $target, array &$visited): bool {
        if (in_array($currentRole, $visited)) {
            return false; // 防止循环引用
        }
        $visited[] = $currentRole;

        // 直接继承检查
        if (isset($policy['inheritance'][$currentRole])) {
            foreach ((array)$policy['inheritance'][$currentRole] as $parentRole) {
                if (self::checkRecursive($policy, $parentRole, $target, $visited)) {
                    return true;
                }
            }
        }

        // 直接权限检查
        return in_array($target, $policy[$currentRole] ?? []);
    }
}