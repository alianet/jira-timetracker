<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Http;

use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;
use App\Kernel\Support\ApiValue;
use App\TimeTracking\Application\Command\AddWorklog;
use App\TimeTracking\Application\Command\DeleteWorklog;
use App\TimeTracking\Application\Command\UpdateWorklog;
use App\TimeTracking\Application\Handler\AddWorklogHandler;
use App\TimeTracking\Application\Handler\DeleteWorklogHandler;
use App\TimeTracking\Application\Handler\UpdateWorklogHandler;
use App\TimeTracking\Application\SavedWorklog;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class WorklogController
{
    /**
     * @param callable(): AddWorklogHandler $addHandler
     * @param callable(): UpdateWorklogHandler $updateHandler
     * @param callable(): DeleteWorklogHandler $deleteHandler
     */
    public function __construct(
        private mixed $addHandler,
        private mixed $updateHandler,
        private mixed $deleteHandler,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function create(Request $request): Response
    {
        $this->verifyCsrf($request);
        $saved = ($this->addHandler)()->handle(new AddWorklog(
            $this->field($request, 'issue'),
            $this->field($request, 'date'),
            $this->field($request, 'time_spent'),
            $this->field($request, 'comment'),
        ));
        $this->logger->info('Jira worklog created.', [
            'issue' => $saved->issue,
            'year' => $saved->year,
            'month' => $saved->month,
        ]);

        return $this->savedRedirect($saved);
    }

    public function update(Request $request): Response
    {
        $this->verifyCsrf($request);
        $saved = ($this->updateHandler)()->handle(new UpdateWorklog(
            $this->field($request, 'issue'),
            $this->field($request, 'date'),
            ApiValue::stringValue($request->attributes['id'] ?? null),
            $this->field($request, 'time_spent'),
            $this->field($request, 'comment'),
        ));
        $this->logger->info('Jira worklog updated.', [
            'issue' => $saved->issue,
            'worklog_id' => ApiValue::stringValue($request->attributes['id'] ?? null),
            'year' => $saved->year,
            'month' => $saved->month,
        ]);

        return $this->savedRedirect($saved);
    }

    public function delete(Request $request): Response
    {
        $this->verifyCsrf($request);
        $saved = ($this->deleteHandler)()->handle(new DeleteWorklog(
            $this->field($request, 'issue'),
            $this->field($request, 'date'),
            ApiValue::stringValue($request->attributes['id'] ?? null),
        ));
        $this->logger->info('Jira worklog deleted.', [
            'issue' => $saved->issue,
            'worklog_id' => ApiValue::stringValue($request->attributes['id'] ?? null),
            'year' => $saved->year,
            'month' => $saved->month,
        ]);

        return $this->savedRedirect($saved);
    }

    /** Transitional compatibility for clients posting the former action/worklog_id contract to /. */
    public function legacy(Request $request): Response
    {
        $id = trim($this->field($request, 'worklog_id'));
        $attributes = $request->attributes;
        $attributes['id'] = $id;
        $legacyRequest = new Request($request->method, $request->path, $request->query, $request->post, $attributes, $request->session);

        if ($this->field($request, 'action') === 'delete') {
            return $this->delete($legacyRequest);
        }

        return $id === '' ? $this->create($legacyRequest) : $this->update($legacyRequest);
    }

    private function verifyCsrf(Request $request): void
    {
        if (!hash_equals(
            ApiValue::stringValue($request->session->get('csrf_token')),
            $this->field($request, 'csrf_token'),
        )) {
            $this->logger->warning('Worklog operation rejected because of invalid CSRF token.', [
                'path' => $request->path,
            ]);
            throw WorkLogRuntimeException::create('Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
        }
    }

    private function field(Request $request, string $name): string
    {
        return ApiValue::stringValue($request->post[$name] ?? null);
    }

    private function savedRedirect(SavedWorklog $saved): Response
    {
        return Response::redirect('/?' . http_build_query([
            'year' => $saved->year,
            'month' => $saved->month,
            'issue' => $saved->issue,
            'saved' => 1,
        ]));
    }
}
