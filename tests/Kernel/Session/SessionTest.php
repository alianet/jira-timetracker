<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Session;

use App\Kernel\Session\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testMutatesTheBackingNativeSession(): void
    {
        $values = ['locale' => 'pl'];
        $session = new Session($values);

        self::assertSame('pl', $session->get('locale'));
        self::assertNull($session->get('missing'));

        $session->set('csrf_token', 'known');
        $session->remove('locale');

        self::assertSame(['csrf_token' => 'known'], $values);

        $session->clear();

        self::assertSame([], $values);
    }
}
