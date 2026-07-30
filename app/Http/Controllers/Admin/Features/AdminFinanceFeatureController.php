<?php

namespace App\Http\Controllers\Admin\Features;

use App\Models\BillingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AdminFinanceFeatureController extends BaseAdminFeatureController
{
    public function updateBillingSettings(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'invoice_day' => ['required', 'integer', 'min:1', 'max:28'],
            'invoice_time' => ['required', 'date_format:H:i'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        BillingSetting::monthlyTuition()->update([
            'invoice_day' => $validated['invoice_day'],
            'invoice_time' => $validated['invoice_time'].':00',
            'default_amount' => $validated['default_amount'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.payments')->with('status', 'Monthly tuition billing settings updated.');
    }

    public function generateMonthlyDues(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        Artisan::call('tuition:generate-monthly --force');

        return redirect()->route('admin.payments')->with('status', trim(Artisan::output()));
    }
}
