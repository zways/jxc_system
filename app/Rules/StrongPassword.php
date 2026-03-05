<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 密码强度策略验证规则
 *
 * 要求：
 * - 最少 8 位
 * - 最多 100 位
 * - 必须包含至少一个大写字母
 * - 必须包含至少一个小写字母
 * - 必须包含至少一个数字
 * - 不能是常见弱密码
 */
class StrongPassword implements ValidationRule
{
    /**
     * 常见弱密码黑名单
     */
    protected static array $commonPasswords = [
        'password', 'password1', 'Password1', 'Password123',
        '12345678', '123456789', '1234567890',
        'qwerty123', 'Qwerty123', 'qwertyui',
        'abc12345', 'Abc12345', 'abcd1234', 'Abcd1234',
        '11111111', '00000000', '88888888',
        'admin123', 'Admin123', 'admin888',
        'test1234', 'Test1234',
        'iloveyou', 'sunshine',
        'Aa123456', 'Aa888888', 'Qq123456',
        'a1234567', 'A1234567',
        'pass1234', 'Pass1234',
        'welcome1', 'Welcome1',
        'p@ssw0rd', 'P@ssw0rd', 'P@ssword1',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('密码必须是字符串');
            return;
        }

        if (mb_strlen($value) < 8) {
            $fail('密码长度不能少于 8 位');
            return;
        }

        if (mb_strlen($value) > 100) {
            $fail('密码长度不能超过 100 位');
            return;
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $fail('密码必须包含至少一个大写字母');
            return;
        }

        if (!preg_match('/[a-z]/', $value)) {
            $fail('密码必须包含至少一个小写字母');
            return;
        }

        if (!preg_match('/[0-9]/', $value)) {
            $fail('密码必须包含至少一个数字');
            return;
        }

        // 检查是否为常见弱密码（不区分前后空格）
        if (in_array(trim($value), static::$commonPasswords, true)) {
            $fail('密码过于简单，请更换一个更复杂的密码');
            return;
        }
    }

    /**
     * 返回前端可用的密码策略描述（方便统一展示）
     */
    public static function description(): string
    {
        return '密码要求：8-100 位，包含大写字母、小写字母和数字';
    }
}
