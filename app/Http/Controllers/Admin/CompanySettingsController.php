<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanySettingsRequest;
use App\Settings\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    /**
     * Show the company settings form for this deployment.
     */
    public function edit(CompanySettings $settings): Response
    {
        return Inertia::render('admin/CompanySettings', [
            'company' => [
                'name' => $settings->name,
                'legal_name' => $settings->legal_name,
                'phone' => $settings->phone,
                'website' => $settings->website,
                'address_line1' => $settings->address_line1,
                'address_line2' => $settings->address_line2,
                'city' => $settings->city,
                'region' => $settings->region,
                'postal_code' => $settings->postal_code,
                'country' => $settings->country,
                'email_domain' => $settings->email_domain,
            ],
        ]);
    }

    /**
     * Update persisted company settings (Spatie).
     */
    public function update(UpdateCompanySettingsRequest $request, CompanySettings $settings): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            $settings->{$key} = $value;
        }

        $settings->save();

        return to_route('admin.company.edit');
    }
}
