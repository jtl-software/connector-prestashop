<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Jtl\Connector\Core\Exception\TokenValidatorException;
use jtl\Connector\Presta\Auth\TokenValidator;
use PHPUnit\Framework\TestCase;

final class TokenValidatorTest extends TestCase
{
    public function testConstructWithEmptyTokenThrowsTokenValidatorException(): void
    {
        $this->expectException(TokenValidatorException::class);
        $this->expectExceptionCode(TokenValidatorException::EMPTY_TOKEN);

        new TokenValidator('');
    }

    public function testConstructWithNonEmptyTokenDoesNotThrow(): void
    {
        $validator = new TokenValidator('secret');
        self::assertInstanceOf(TokenValidator::class, $validator);
    }

    public function testValidateWithMatchingTokenReturnsTrue(): void
    {
        $validator = new TokenValidator('my-secret-token');
        self::assertTrue($validator->validate('my-secret-token'));
    }

    public function testValidateWithWrongTokenReturnsFalse(): void
    {
        $validator = new TokenValidator('my-secret-token');
        self::assertFalse($validator->validate('wrong-token'));
    }

    public function testValidateWithEmptyStringReturnsFalse(): void
    {
        $validator = new TokenValidator('my-secret-token');
        self::assertFalse($validator->validate(''));
    }

    public function testValidateIsCaseSensitive(): void
    {
        $validator = new TokenValidator('Secret');
        self::assertFalse($validator->validate('secret'));
        self::assertFalse($validator->validate('SECRET'));
        self::assertTrue($validator->validate('Secret'));
    }

    public function testValidateWithWhitespaceOnlyTokenReturnsFalse(): void
    {
        $validator = new TokenValidator('token');
        self::assertFalse($validator->validate(' '));
    }

    public function testConstructWithWhitespaceOnlyTokenDoesNotThrow(): void
    {
        // A token consisting only of whitespace is non-empty and therefore valid
        $validator = new TokenValidator('   ');
        self::assertTrue($validator->validate('   '));
    }
}
