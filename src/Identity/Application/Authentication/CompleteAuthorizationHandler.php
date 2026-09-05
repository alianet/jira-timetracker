<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;

final readonly class CompleteAuthorizationHandler
{
    public function __construct(
        private AuthorizationGateway $gateway,
        private AccessTokenStore $store,
    ) {}

    public function isInteractive(): bool
    {
        return $this->gateway->isInteractive();
    }

    public function handle(string $expectedState, string $actualState, ?string $error, string $code): void
    {
        if ($expectedState === '' || !hash_equals($expectedState, $actualState)) {
            throw WorkLogRuntimeException::create('Nie udało się potwierdzić bezpieczeństwa logowania. Spróbuj ponownie.');
        }
        if ($error !== null) {
            throw WorkLogRuntimeException::create('Logowanie Atlassian zostało anulowane lub odrzucone.');
        }
        if (trim($code) === '') {
            throw WorkLogRuntimeException::create('Atlassian nie zwrócił kodu autoryzacyjnego.');
        }

        $this->store->save($this->gateway->exchange(trim($code)));
    }
}
