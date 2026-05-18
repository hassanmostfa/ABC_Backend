<?php

namespace App\Support;

use RuntimeException;

final class OctopusApiToken
{
    public const PREFIX = 'abc_';

    /** Known placeholder shipped in .env.example — must never be used in any environment. */
    public const FORBIDDEN_PLACEHOLDER = 'abc_change_me_to_a_secret_value';

    private const MIN_SECRET_BYTES = 32;

    /** @var list<string> */
    private const FORBIDDEN_VALUES = [
        self::FORBIDDEN_PLACEHOLDER,
        'abc_changeme',
        'abc_change_me',
        'abc_secret',
        'abc_test',
        'abc_token',
    ];

    public static function generate(): string
    {
        return self::PREFIX . bin2hex(random_bytes(self::MIN_SECRET_BYTES));
    }

    public static function isForbidden(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        return in_array($token, self::FORBIDDEN_VALUES, true);
    }

    public static function isConfiguredSecurely(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        if (!str_starts_with($token, self::PREFIX)) {
            return false;
        }

        if (self::isForbidden($token)) {
            return false;
        }

        $secret = substr($token, strlen(self::PREFIX));

        return strlen($secret) >= self::MIN_SECRET_BYTES * 2;
    }

    public static function assertSafeForBoot(?string $token): void
    {
        if (self::isForbidden($token)) {
            throw new RuntimeException(
                'OCTOPUS_API_TOKEN is set to a known insecure placeholder. '
                . 'Generate a new token: php -r "echo \'abc_\'.bin2hex(random_bytes(32)).PHP_EOL;"'
            );
        }

        if (app()->environment('production') && !self::isConfiguredSecurely($token)) {
            throw new RuntimeException(
                'OCTOPUS_API_TOKEN must be set in production (prefix abc_, at least 32 random bytes after the prefix). '
                . 'Generate: php -r "echo \'abc_\'.bin2hex(random_bytes(32)).PHP_EOL;"'
            );
        }
    }
}
