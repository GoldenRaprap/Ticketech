<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordStaffActivity
{
    public function __construct(private AuditActivityLogger $logger) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isStaff() || $request->routeIs('staff.ticket-updates')) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->recordActivity($request, $user, null, $exception::class);

            throw $exception;
        }

        $this->recordActivity($request, $user, $response->getStatusCode());

        return $response;
    }

    private function recordActivity(
        Request $request,
        User $actor,
        ?int $responseStatus,
        ?string $exceptionType = null,
    ): void {
        $routeName = $request->route()?->getName() ?? $request->path();
        $role = $actor->isAdmin() ? 'admin' : 'staff';
        $isPageView = in_array($request->method(), ['GET', 'HEAD'], true);
        $action = $isPageView ? 'viewed' : match ($request->method()) {
            'POST' => 'submitted',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'performed an action on',
        };
        $routeLabel = Str::headline(Str::after($routeName, $role.'.'));
        $subject = $this->routeSubject($request);
        $description = ucfirst($role).' '.$action.' '.$routeLabel.'.';

        if ($subject instanceof Model) {
            $description = ucfirst($role).' '.$action.' '.class_basename($subject)
                .' '.($this->subjectLabel($subject) ?? '#'.$subject->getRouteKey())
                .' via '.$routeName.'.';
        }

        $this->logger->record(
            $request,
            $role.($isPageView ? '_page_viewed' : '_action_performed'),
            $description,
            $subject instanceof Model ? $subject : null,
            array_filter([
                'response_status' => $responseStatus,
                'outcome' => $exceptionType !== null
                    ? 'exception'
                    : ($responseStatus !== null && $responseStatus >= 400
                        ? 'failed'
                        : ($responseStatus !== null && $responseStatus >= 300 ? 'redirected' : 'completed')),
                'exception_type' => $exceptionType,
            ], static fn (mixed $value): bool => $value !== null),
            $actor,
        );
    }

    private function routeSubject(Request $request): ?Model
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }

    private function subjectLabel(Model $subject): ?string
    {
        foreach (['name', 'ticket_number', 'title'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
