<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Security\FrontendStopRequestValidator;
use PHPUnit\Framework\TestCase;

final class FrontendStopRequestValidatorTest extends TestCase
{
    public function testAllowsPostMethod(): void
    {
        $validator = new FrontendStopRequestValidator();

        self::assertTrue($validator->isMethodAllowed('POST'));
        self::assertTrue($validator->isMethodAllowed('post'));
    }

    public function testDeniesNonPostMethod(): void
    {
        $validator = new FrontendStopRequestValidator();

        self::assertFalse($validator->isMethodAllowed('GET'));
        self::assertFalse($validator->isMethodAllowed('PUT'));
    }

    public function testValidatesNonceWithExpectedAction(): void
    {
        $validator = new FrontendStopRequestValidator();

        $ok = $validator->isNonceValid('nonce-value', static function (string $nonce, string $action): bool {
            return $nonce === 'nonce-value' && $action === 'impersonate-stop';
        });

        self::assertTrue($ok);
    }

    public function testDeniesMissingNonce(): void
    {
        $validator = new FrontendStopRequestValidator();

        self::assertFalse($validator->isNonceValid('', static function (): bool {
            return true;
        }));
    }

    public function testDeniesInvalidNonceFromVerifier(): void
    {
        $validator = new FrontendStopRequestValidator();

        $ok = $validator->isNonceValid('nonce-value', static function (): bool {
            return false;
        });

        self::assertFalse($ok);
    }
}
