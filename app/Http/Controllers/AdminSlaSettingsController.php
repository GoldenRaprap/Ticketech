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
        TicketPriorityClassifier $priorityClassifier,
        TicketResolutionSla $resolutionSla,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $rules = [
            'targets' => ['required', 'array:urgent,high,medium,low'],
            'priority_keywords' => ['sometimes', 'required', 'array:urgent,high,medium,low'],
        ];

        foreach (['urgent', 'high', 'medium', 'low'] as $priority) {
            $rules["priority_keywords.{$priority}"] = ['sometimes', 'nullable', 'string', 'max:2000'];

            foreach (['first_response', 'resolution'] as $target) {
                $rules["targets.{$priority}.{$target}.value"] = ['required', 'integer', 'between:1,999'];
                $rules["targets.{$priority}.{$target}.unit"] = ['required', 'in:business_hour,business_day'];
            }
        }

        $validated = $request->validate($rules);
        $slaSettings->update($validated['targets']);
        $resolutionSla->refreshOpenTickets();
        $priorityKeywords = $validated['priority_keywords'] ?? null;

        if ($priorityKeywords !== null) {
            $priorityClassifier->update($priorityKeywords);
        }

        $auditLogger->record(
            $request,
            'ticket_options_updated',
            'Updated service level targets and automatic ticket priority rules.',
            properties: [
                'targets' => $validated['targets'],
                'priority_keywords' => $priorityKeywords,
            ],
        );

        return to_route('admin.sla-settings')->with('status', 'Ticket options updated.');
    }
}
