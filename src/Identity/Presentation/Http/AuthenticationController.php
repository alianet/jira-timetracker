<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\Authentication\CompleteAuthorizationHandler;
use App\Identity\Application\Authentication\LogoutHandler;
use App\Identity\Application\Authentication\StartAuthorizationHandler;
use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;
use App\Kernel\Support\ApiValue;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class AuthenticationController
{
    /**
     * @param \Closure(): StartAuthorizationHandler $start
     * @param \Closure(): CompleteAuthorizationHandler $complete
     * @param \Closure(): LogoutHandler $logout
     */
    public function __construct(
        private \Closure $start,
        private \Closure $complete,
        private \Closure $logout,
        private SessionSecurity $security,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function login(Request $request): Response
    {
        $this->logger->info('User started Atlassian authentication.');
        $state = $this->security->randomToken();
        $url = ($this->start)()->handle($state);
        if ($url === null) {
            $this->logger->debug('OAuth login skipped for non-interactive authentication mode.');
            return Response::redirect('/', 302);
        }
        $request->session->set('oauth_state', $state);

        return Response::redirect($url, 302);
    }

    public function callback(Request $request): Response
    {
        $handler = ($this->complete)();
        if (!$handler->isInteractive()) {
            $this->logger->debug('OAuth callback skipped for non-interactive authentication mode.');
            return Response::redirect('/', 302);
        }
        $expectedState = ApiValue::stringValue($request->session->get('oauth_state'));
        $request->session->remove('oauth_state');
        $handler->handle(
            $expectedState,
            ApiValue::stringValue($request->query['state'] ?? null),
            isset($request->query['error']) ? ApiValue::stringValue($request->query['error']) : null,
            ApiValue::stringValue($request->query['code'] ?? null),
        );
        $this->security->regenerateId();
        $request->session->set('csrf_token', $this->security->randomToken());
        $this->logger->info('User completed Atlassian authentication.');

        return Response::redirect('/');
    }

    public function logout(Request $request): Response
    {
        if (!hash_equals(
            ApiValue::stringValue($request->session->get('csrf_token')),
            ApiValue::stringValue($request->post['csrf_token'] ?? null),
        )) {
            $this->logger->warning('Logout rejected because of invalid CSRF token.');
            throw WorkLogRuntimeException::create('Nieprawidłowy token wylogowania.');
        }
        ($this->logout)()->handle();
        $request->session->clear();
        $this->security->regenerateId();
        $this->logger->info('User logged out.');

        return Response::redirect('/');
    }
}
