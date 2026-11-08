<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Services\AuditActivityLogger;
use App\Services\SlaSettings;
use App\Services\TicketPriorityClassifier;
use App\Services\TicketResolutionSla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSlaSettingsController extends Controller
{
    public function index(SlaSettings $slaSettings, TicketPriorityClassifier $priorityClassifier): View
    {
        return view('staff.sla-settings', [
            'targets' => $slaSettings->targets(),
            'priorityKeywords' => $priorityClassifier->keywords(),
            'departments' => Department::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'categories' => Category::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function update(
        Request $request,
        SlaSettings $slaSettings,
        TicketResolutionSla $resolutionSla,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $rules = [
            'targets' => ['required', 'array:urgent,high,medium,low'],
        ];

        foreach (['urgent', 'high', 'medium', 'low'] as $priority) {
            foreach (['first_response', 'resolution'] as $target) {
                $rules["targets.{$priority}.{$target}.value"] = ['required', 'integer', 'between:1,999'];
                $rules["targets.{$priority}.{$target}.unit"] = ['required', 'in:business_hour,business_day'];
            }
        }

        $validated = $request->validate($rules);
        $slaSettings->update($validated['targets']);
        $resolutionSla->refreshOpenTickets();

        $auditLogger->record(
            $request,
            'service_targets_updated',
            'Updated service level targets.',
            properties: [
                'targets' => $validated['targets'],
            ],
        );

        return to_route('admin.sla-settings')->with('status', 'Service targets updated.');
    }

    public function updatePriorityKeywords(
        Request $request,
        TicketPriorityClassifier $priorityClassifier,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $rules = [
            'priority_keywords' => ['required', 'array:urgent,high,medium,low'],
        ];

        foreach (['urgent', 'high', 'medium', 'low'] as $priority) {
            $rules["priority_keywords.{$priority}"] = ['present', 'nullable', 'string', 'max:2000'];
        }

        $validated = $request->validate($rules);
        $priorityClassifier->update($validated['priority_keywords']);

        $auditLogger->record(
            $request,
            'ticket_priority_rules_updated',
            'Updated automatic ticket priority keyword rules.',
            properties: ['priority_keywords' => $validated['priority_keywords']],
        );

        return to_route('admin.sla-settings')->with('status', 'Priority keyword rules updated.');
    }
}
