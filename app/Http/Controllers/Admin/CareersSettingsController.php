<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCareersSettingsRequest;
use App\Settings\CareersSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CareersSettingsController extends Controller
{
    public function edit(CareersSettings $settings): Response
    {
        return Inertia::render('admin/CareersSettings', [
            'notification_emails_text' => implode("\n", $settings->notification_emails ?? []),
        ]);
    }

    public function update(UpdateCareersSettingsRequest $request, CareersSettings $settings): RedirectResponse
    {
        $settings->notification_emails = $request->validated('notification_emails');
        $settings->save();

        return to_route('admin.careers-settings.edit')->with('toast', __('Careers notification settings saved.'));
    }
}
