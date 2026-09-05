<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class HttpTransportException extends \RuntimeException
{
    private function __construct(
        public readonly string $reason,
        public readonly ?int $status = null,
        public readonly string $responseBody = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($previous?->getMessage() ?? $reason, 0, $previous);
    }

    public static function connectionFailed(\Throwable $previous): self
    {
        return new self('connection_failed', previous: $previous);
    }

    public static function unsuccessfulResponse(int $status, string $responseBody): self
    {
        return new self('unsuccessful_response', $status, $responseBody);
    }

    public static function unexpectedJsonPayload(): self
    {
        return new self('unexpected_json_payload');
    }
}
