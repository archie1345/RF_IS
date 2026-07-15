<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Features\AdminAttendanceReportController;
use App\Http\Controllers\Admin\Features\AdminEventFeatureController;
use App\Http\Controllers\Admin\Features\AdminFinanceFeatureController;
use App\Http\Controllers\Admin\Features\AdminPeopleFeatureController;
use App\Http\Controllers\Admin\Features\AdminScheduleFeatureController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * @deprecated Admin feature logic has been split into controllers under
 * App\Http\Controllers\Admin\Features. Keep this shim only for older direct
 * references; routes should use the feature-specific controllers.
 */
class AdminFeatureController extends Controller
{
    public function attendance(Request $request, AdminAttendanceReportController $controller): Response
    {
        return $controller->athletes($request);
    }

    public function instructorAttendance(Request $request, AdminAttendanceReportController $controller): Response
    {
        return $controller->coaches($request);
    }

    public function payments(Request $request, AdminFinanceFeatureController $controller): Response
    {
        return $controller->index($request);
    }

    public function updateBillingSettings(Request $request, AdminFinanceFeatureController $controller): RedirectResponse
    {
        return $controller->updateBillingSettings($request);
    }

    public function generateMonthlyDues(Request $request, AdminFinanceFeatureController $controller): RedirectResponse
    {
        return $controller->generateMonthlyDues($request);
    }

    public function financeIncome(Request $request, AdminFinanceFeatureController $controller): Response
    {
        return $controller->index($request);
    }

    public function financeOutput(Request $request, AdminFinanceFeatureController $controller): Response
    {
        return $controller->index($request);
    }

    public function monthlyDues(Request $request, AdminFinanceFeatureController $controller): Response
    {
        return $controller->index($request);
    }

    public function members(Request $request, AdminPeopleFeatureController $controller): Response
    {
        return $controller->members($request);
    }

    public function instructors(Request $request, AdminPeopleFeatureController $controller): Response
    {
        return $controller->instructors($request);
    }

    public function events(Request $request, AdminEventFeatureController $controller): Response
    {
        return $controller->index($request);
    }

    public function eventHistory(Request $request, AdminEventFeatureController $controller): Response
    {
        return $controller->history($request);
    }

    public function eventSchedule(Request $request, AdminEventFeatureController $controller): Response
    {
        return $controller->index($request);
    }

    public function dailySchedules(Request $request, AdminScheduleFeatureController $controller): Response
    {
        return $controller->daily($request);
    }

    public function periodicStats(Request $request, AdminScheduleFeatureController $controller): Response
    {
        return $controller->disabledReports($request);
    }
}
