<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Shared\Infrastructure\Jira;

use App\Shared\Infrastructure\Http\HttpTransportException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class JiraErrorResponse
{
    public static function summary(int $status, TranslatorInterface $translator): string
    {
        return match ($status) {
            400 => $translator->trans('jira.error.bad_request'),
            401 => $translator->trans('jira.error.unauthorized'),
            403 => $translator->trans('jira.error.forbidden'),
            404 => $translator->trans('jira.error.not_found'),
            429 => $translator->trans('jira.error.rate_limit'),
            default => $translator->trans('jira.error.generic', ['{status}' => (string) $status]),
        };
    }

    public static function rateLimitMessage(
        HttpTransportException $exception,
        TranslatorInterface $translator,
    ): string {
        $retryAfter = $exception->retryAfterSeconds();

        return $retryAfter === null
            ? $translator->trans('jira.error.rate_limit')
            : $translator->trans('jira.error.rate_limit_retry_after', ['{seconds}' => (string) $retryAfter]);
    }
}
