<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Identity\Infrastructure\Session;

use App\Identity\Application\Authentication\AuthenticatedAccess;
use App\Identity\Infrastructure\Session\SessionAccessTokenStore;
use App\Identity\Infrastructure\Session\SessionTokenCipher;
use App\Kernel\Session\Session;
use PHPUnit\Framework\TestCase;

final class SessionAccessTokenStoreTest extends TestCase
{
    private const string KEY = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

    public function testTokensAreEncryptedInSessionAndCanBeLoaded(): void
    {
        $session = [];
        $store = $this->store($session);
        $access = new AuthenticatedAccess(
            'access-secret',
            'refresh-secret',
            123456,
            'cloud-id',
            'https://jira.example',
            'Example Jira',
        );

        $store->save($access);

        self::assertIsString($session['atlassian']);
        self::assertStringStartsWith('v1.', $session['atlassian']);
        self::assertStringNotContainsString('access-secret', $session['atlassian']);
        self::assertStringNotContainsString('refresh-secret', $session['atlassian']);
        self::assertEquals($access, $store->load());
    }

    public function testTamperedCiphertextIsRejectedAndRemoved(): void
    {
        $session = [];
        $store = $this->store($session);
        $store->save(new AuthenticatedAccess('access', 'refresh', 123, 'cloud', 'site', 'name'));
        $session['atlassian'][5] = $session['atlassian'][5] === 'A' ? 'B' : 'A';

        self::assertNull($store->load());
        self::assertArrayNotHasKey('atlassian', $session);
    }

    public function testPlaintextLegacySessionIsRejectedAndRemoved(): void
    {
        $session = ['atlassian' => ['access_token' => 'secret', 'cloud_id' => 'cloud']];

        self::assertNull($this->store($session)->load());
        self::assertArrayNotHasKey('atlassian', $session);
    }

    private function store(array &$session): SessionAccessTokenStore
    {
        return new SessionAccessTokenStore(new Session($session), new SessionTokenCipher(self::KEY));
    }
}
