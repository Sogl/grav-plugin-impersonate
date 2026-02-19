<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Security\AdminTaskRequestValidator;
use PHPUnit\Framework\TestCase;

final class AdminTaskRequestValidatorTest extends TestCase
{
    public function testDeniesMissingNonce(): void
    {
        $validator = new AdminTaskRequestValidator();

        self::assertFalse($validator->isNonceValid('', static function (): bool {
            return true;
        }));
        self::assertFalse($validator->isNonceValid(null, static function (): bool {
            return true;
        }));
    }

    public function testValidatesNonceWithExpectedAction(): void
    {
        $validator = new AdminTaskRequestValidator();

        $ok = $validator->isNonceValid('nonce-value', static function (string $nonce, string $action): bool {
            return $nonce === 'nonce-value' && $action === 'admin-form';
        });

        self::assertTrue($ok);
    }

    public function testDeniesInvalidNonceFromVerifier(): void
    {
        $validator = new AdminTaskRequestValidator();

        $ok = $validator->isNonceValid('nonce-value', static function (): bool {
            return false;
        });

        self::assertFalse($ok);
    }
}

