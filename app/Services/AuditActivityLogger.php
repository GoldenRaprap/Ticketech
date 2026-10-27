<?php

namespace App\Services;

use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        Request $request,
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): TicketEvent {
        $actor ??= $request->user();

        return TicketEvent::query()->create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'Unknown user',
            'event' => $event,
            'description' => $description,
            'request_method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject ? (string) $subject->getRouteKey() : null,
            'subject_name' => $this->subjectName($subject),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => $properties,
        ]);
    }

    private function subjectName(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        foreach (['name', 'ticket_number', 'title'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
