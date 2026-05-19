<?php

namespace Tests\Unit\Auth;

use App\Services\Auth\AppleIdTokenVerifier;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AppleIdTokenVerifierTest extends TestCase
{
    public function test_audience_matches_string_or_array(): void
    {
        $verifier = new AppleIdTokenVerifier;
        $method = new ReflectionMethod($verifier, 'audienceMatches');
        $method->setAccessible(true);

        $allowed = ['com.tandilapp.tandil'];

        $this->assertTrue($method->invoke($verifier, 'com.tandilapp.tandil', $allowed));
        $this->assertTrue($method->invoke($verifier, ['com.other.app', 'com.tandilapp.tandil'], $allowed));
        $this->assertFalse($method->invoke($verifier, 'com.other.app', $allowed));
        $this->assertFalse($method->invoke($verifier, ['com.other.app'], $allowed));
    }
}
