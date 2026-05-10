<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLeaveSettingsRequest;
use App\Settings\LeaveSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LeaveSettingsController extends Controller
{
    public function edit(LeaveSettings $settings): Response
    {
        return Inertia::render('admin/LeaveSettings', [
            'notification_emails_text' => implode("\n", $settings->notification_emails ?? []),
        ]);
    }

    public function update(UpdateLeaveSettingsRequest $request, LeaveSettings $settings): RedirectResponse
    {
        $settings->notification_emails = $request->validated('notification_emails');
        $settings->save();

        return to_route('admin.leave-settings.edit')->with('toast', __('Leave notification settings saved.'));
    }
}
