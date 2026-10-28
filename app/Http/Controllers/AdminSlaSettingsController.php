<?php

namespace App\Http\Controllers;

use App\Services\AuditActivityLogger;
use App\Services\SlaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSlaSettingsController extends Controller
{
    public function index(SlaSettings $slaSettings): View
    {
        return view('staff.sla-settings', [
            'targets' => $slaSettings->targets(),
        ]);
    }

    public function update(
        Request $request,
        SlaSettings $slaSettings,
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

        $auditLogger->record(
            $request,
            'sla_settings_updated',
            'Updated service level targets.',
            properties: ['targets' => $validated['targets']],
        );

        return to_route('admin.sla-settings')->with('status', 'Service level targets updated.');
    }
}
