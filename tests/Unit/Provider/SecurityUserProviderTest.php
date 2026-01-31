<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Tests\Unit\Provider;

use CorentinBoutillier\InvoiceBundle\DTO\UserData;
use CorentinBoutillier\InvoiceBundle\Provider\SecurityUserProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(SecurityUserProvider::class)]
final class SecurityUserProviderTest extends TestCase
{
    // ========================================
    // Null Security Tests
    // ========================================

    public function testGetCurrentUserReturnsNullWhenSecurityIsNull(): void
    {
        $provider = new SecurityUserProvider(null);

        $result = $provider->getCurrentUser();

        self::assertNull($result);
    }

    // ========================================
    // No Authenticated User Tests
    // ========================================

    public function testGetCurrentUserReturnsNullWhenNoUserIsAuthenticated(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $provider = new SecurityUserProvider($security);

        $result = $provider->getCurrentUser();

        self::assertNull($result);
    }

    // ========================================
    // Basic User Extraction Tests
    // ========================================

    public function testGetCurrentUserReturnsUserDataWithIdentifier(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('john@example.com');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $provider = new SecurityUserProvider($security);

        $result = $provider->getCurrentUser();

        self::assertInstanceOf(UserData::class, $result);
        self::assertSame('john@example.com', $result->id);
        // Fallback to identifier when no name method available
        self::assertSame('john@example.com', $result->name);
        self::assertNull($result->email);
    }

    // ========================================
    // Name Extraction Tests (getFullName)
    // ========================================

    public function testGetCurrentUserExtractsNameFromGetFullName(): void
    {
        $user = $this->createUserWithFullName('john@example.com', 'John Doe');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $provider = new SecurityUserProvider($security);

        $result = $provider->getCurrentUser();

        self::assertInstanceOf(UserData::class, $result);
        self::assertSame('john@example.com', $result->id);
        self::assertSame('John Doe', $result->name);
    }

    // ========================================
    // Name Extraction Tests (getName)
    // ========================================

    public function testGetCurrentUserExtractsNameFromGetName(): void
    {
        $user = $this->createUserWithName('jane@example.com', 'Jane Smith');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $provider = new SecurityUserProvider($security);

        $result = $provider->getCurrentUser();

        self::assertInstanceOf(UserData::class, $result);
        self::assertSame('jane@example.com', $result->id);
        self::assertSame('Jane Smith', $result->name);
    }

    // ========================================
    // Name Extraction Tests (getUsername)
    // ========================================

    public function testGetCurrentUserExtractsNameFromGetUsername(): void
    {
        $user = $this->createUserWithUsername('admin@example.com', 'admin_user');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $provider = new SecurityUserProvider($security);

        $result = $provider->getCurrentUser();

        self::assertInstanceOf(UserData::class, $result);
        self::assertSame('admin@example.com', $result->id);
        self::assertSame('admin_user', $result->name);
    }

    // ========================================
    // Email Extraction Tests
    // ========================================

    public function testGetCurrentUserExtractsEmail(): void
    {
        $user = $this->createUserWithEmail('user123', 'contact@example.com');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $provider = new SecurityUserProvider($security);

        $result = $provider->getCurrentUser();

        self::assertInstanceOf(UserData::class, $result);
        self::assertSame('user123', $result->id);
        self::assertSame('contact@example.com', $result->email);
    }

    // ========================================
    // Full User Data Extraction Tests
    // ========================================

    public function testGetCurrentUserExtractsAllData(): void
    {
        $user = $this->createFullUser('user@example.com', 'John Doe', 'john@personal.com');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $provider = new SecurityUserProvider($security);

        $result = $provider->getCurrentUser();

        self::assertInstanceOf(UserData::class, $result);
        self::assertSame('user@example.com', $result->id);
        self::assertSame('John Doe', $result->name);
        self::assertSame('john@personal.com', $result->email);
    }

    // ========================================
    // Priority Tests (getFullName > getName > getUsername)
    // ========================================

    public function testGetFullNameHasPriorityOverGetName(): void
    {
        $user = $this->createUserWithBothFullNameAndName('user@test.com', 'Full Name', 'Simple Name');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $provider = new SecurityUserProvider($security);

        $result = $provider->getCurrentUser();

        self::assertInstanceOf(UserData::class, $result);
        self::assertSame('Full Name', $result->name);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createUserWithFullName(string $identifier, string $fullName): object
    {
        return new class($identifier, $fullName) implements UserInterface {
            public function __construct(
                private readonly string $identifier,
                private readonly string $fullName,
            ) {
            }

            public function getUserIdentifier(): string
            {
                return $this->identifier;
            }

            public function getFullName(): string
            {
                return $this->fullName;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
    }

    private function createUserWithName(string $identifier, string $name): object
    {
        return new class($identifier, $name) implements UserInterface {
            public function __construct(
                private readonly string $identifier,
                private readonly string $name,
            ) {
            }

            public function getUserIdentifier(): string
            {
                return $this->identifier;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
    }

    private function createUserWithUsername(string $identifier, string $username): object
    {
        return new class($identifier, $username) implements UserInterface {
            public function __construct(
                private readonly string $identifier,
                private readonly string $username,
            ) {
            }

            public function getUserIdentifier(): string
            {
                return $this->identifier;
            }

            public function getUsername(): string
            {
                return $this->username;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
    }

    private function createUserWithEmail(string $identifier, string $email): object
    {
        return new class($identifier, $email) implements UserInterface {
            public function __construct(
                private readonly string $identifier,
                private readonly string $email,
            ) {
            }

            public function getUserIdentifier(): string
            {
                return $this->identifier;
            }

            public function getEmail(): string
            {
                return $this->email;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
    }

    private function createFullUser(string $identifier, string $fullName, string $email): object
    {
        return new class($identifier, $fullName, $email) implements UserInterface {
            public function __construct(
                private readonly string $identifier,
                private readonly string $fullName,
                private readonly string $email,
            ) {
            }

            public function getUserIdentifier(): string
            {
                return $this->identifier;
            }

            public function getFullName(): string
            {
                return $this->fullName;
            }

            public function getEmail(): string
            {
                return $this->email;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
    }

    private function createUserWithBothFullNameAndName(string $identifier, string $fullName, string $name): object
    {
        return new class($identifier, $fullName, $name) implements UserInterface {
            public function __construct(
                private readonly string $identifier,
                private readonly string $fullName,
                private readonly string $name,
            ) {
            }

            public function getUserIdentifier(): string
            {
                return $this->identifier;
            }

            public function getFullName(): string
            {
                return $this->fullName;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
    }
}
