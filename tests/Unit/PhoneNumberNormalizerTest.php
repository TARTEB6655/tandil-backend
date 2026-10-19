<?php

namespace Tests\Unit;

use App\Support\PhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    public function test_uae_local_mobile_to_e164(): void
    {
        $this->assertSame('971501234567', PhoneNumberNormalizer::internationalDigits('0501234567'));
        $this->assertSame('+971501234567', PhoneNumberNormalizer::toE164('0501234567'));
    }

    public function test_india_ten_digit_without_country_code_to_e164(): void
    {
        $this->assertSame('919816025872', PhoneNumberNormalizer::internationalDigits('9816025872'));
        $this->assertSame('+919816025872', PhoneNumberNormalizer::toE164('9816025872'));
    }

    public function test_lookup_variants_include_india_local_and_international(): void
    {
        $variants = PhoneNumberNormalizer::lookupVariants('9816025872');
        $this->assertContains('9816025872', $variants);
        $this->assertContains('919816025872', $variants);
        $this->assertContains('+919816025872', $variants);
    }
}
