<?php

namespace Tests\Unit;

use App\Support\KuwaitPhone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KuwaitPhoneTest extends TestCase
{
    #[DataProvider('phoneProvider')]
    public function test_without_country_code(string $input, string $expected): void
    {
        $this->assertSame($expected, KuwaitPhone::withoutCountryCode($input));
    }

    public static function phoneProvider(): array
    {
        return [
            'local 8 digits' => ['50123456', '50123456'],
            'with plus prefix' => ['+96550123456', '50123456'],
            'with 965 prefix' => ['96550123456', '50123456'],
            'with 00 international prefix' => ['0096550123456', '50123456'],
            'double country code from otp storage' => ['96596550123456', '50123456'],
            'formatted with spaces' => ['+965 5012 3456', '50123456'],
        ];
    }
}
