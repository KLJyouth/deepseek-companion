<?php
namespace Libs;

class SanitizeHelper {
    public static function sanitize($input, $type = 'string') {
        switch ($type) {
            case 'string':
                return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
            case 'email':
                $sanitized = filter_var($input, FILTER_SANITIZE_EMAIL);
                return filter_var($sanitized, FILTER_VALIDATE_EMAIL) ? $sanitized : '';
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
}
?>