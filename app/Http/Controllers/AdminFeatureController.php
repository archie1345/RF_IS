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
    public function attendance(Request $request): Response
    {
        return app(AdminAttendanceReportController::class)->athletes($request);
    }

    public function instructorAttendance(Request $request): Response
    {
        return app(AdminAttendanceReportController::class)->coaches($request);
    }

    public function payments(Request $request): Response
    {
        return app(AdminFinanceFeatureController::class)->index($request);
    }

    public function updateBillingSettings(Request $request): RedirectResponse
    {
        return app(AdminFinanceFeatureController::class)->updateBillingSettings($request);
    }

    public function generateMonthlyDues(Request $request): RedirectResponse
    {
        return app(AdminFinanceFeatureController::class)->generateMonthlyDues($request);
    }

    public function financeIncome(Request $request): Response
    {
        return app(AdminFinanceFeatureController::class)->index($request);
    }

    public function financeOutput(Request $request): Response
    {
        return app(AdminFinanceFeatureController::class)->index($request);
    }

    public function monthlyDues(Request $request): Response
    {
        return app(AdminFinanceFeatureController::class)->index($request);
    }

    public function members(Request $request): Response
    {
        return app(AdminPeopleFeatureController::class)->members($request);
    }

    public function instructors(Request $request): Response
    {
        return app(AdminPeopleFeatureController::class)->instructors($request);
    }

    public function events(Request $request): Response
    {
        return app(AdminEventFeatureController::class)->index($request);
    }

    public function eventHistory(Request $request): Response
    {
        return app(AdminEventFeatureController::class)->history($request);
    }

    public function eventSchedule(Request $request): Response
    {
        return app(AdminEventFeatureController::class)->index($request);
    }

    public function dailySchedules(Request $request): Response
    {
        return app(AdminScheduleFeatureController::class)->daily($request);
    }

    public function periodicStats(Request $request): Response
    {
        return app(AdminScheduleFeatureController::class)->disabledReports($request);
    }
}
