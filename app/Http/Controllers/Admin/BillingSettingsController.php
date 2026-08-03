<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingRule;
use App\Models\BillingSetting;
use App\Models\Branch;
use App\Models\Group;
use App\Services\BillingInvoiceGenerator;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BillingSettingsController extends Controller
{
    public function __construct(private readonly BillingInvoiceGenerator $generator) {}

    public function index(): Response
    {
        $setting = BillingSetting::query()->where('name', 'monthly_tuition')->first();
        $rules = BillingRule::query()
            ->with(['branch:branch_id,branch_name', 'group:group_id,group_name,branch_id'])
            ->withCount('payments')
            ->where(function ($query): void {
                $query
                    ->where('charge_kind', BillingRule::KIND_ONE_TIME)
                    ->orWhere(function ($monthly): void {
                        $monthly
                            ->where('charge_kind', BillingRule::KIND_MONTHLY)
                            ->where(function ($scope): void {
                                $scope->whereNotNull('branch_id')->orWhereNotNull('group_id');
                            });
                    });
            })
            ->orderBy('charge_kind')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/BillingSettingsPage', [
            'setting' => [
                'invoice_day' => (int) ($setting?->invoice_day ?? 1),
                'invoice_time' => substr((string) ($setting?->invoice_time ?? '01:10:00'), 0, 5),
                'default_amount' => (float) ($setting?->default_amount ?? 150000),
                'is_active' => (bool) ($setting?->is_active ?? true),
            ],
            'rules' => $rules->map(fn (BillingRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'charge_kind' => $rule->charge_kind,
                'payment_type' => $rule->payment_type,
                'amount' => (float) $rule->amount,
                'branch_id' => $rule->branch_id,
                'group_id' => $rule->group_id,
                'scope' => $rule->scopeLabel(),
                'due_days' => $rule->due_days,
                'effective_from' => $rule->effective_from?->toDateString(),
                'effective_until' => $rule->effective_until?->toDateString(),
                'is_active' => $rule->is_active,
                'notes' => $rule->notes,
                'payments_count' => $rule->payments_count,
            ]),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('branch_name')
                ->get(['branch_id', 'branch_name'])
                ->map(fn (Branch $branch): array => ['value' => $branch->branch_id, 'label' => $branch->branch_name]),
            'groups' => Group::query()
                ->where('is_active', true)
                ->with('branch:branch_id,branch_name')
                ->orderBy('group_name')
                ->get(['group_id', 'group_name', 'branch_id'])
                ->map(fn (Group $group): array => [
                    'value' => $group->group_id,
                    'label' => trim($group->group_name.' — '.($group->branch?->branch_name ?? 'Tanpa cabang')),
                    'branch_id' => $group->branch_id,
                ]),
            'metrics' => [
                ['label' => 'Aturan bulanan aktif', 'value' => (string) $rules->where('charge_kind', BillingRule::KIND_MONTHLY)->where('is_active', true)->count(), 'detail' => 'Tarif khusus di luar tarif default', 'tone' => 'info'],
                ['label' => 'Tarif default', 'value' => $this->rupiah((float) ($setting?->default_amount ?? 150000)), 'detail' => 'Dipakai bila tidak ada aturan khusus', 'tone' => 'success'],
                ['label' => 'Jadwal otomatis', 'value' => ($setting?->is_active ?? true) ? 'Aktif' : 'Nonaktif', 'detail' => 'Tanggal '.($setting?->invoice_day ?? 1).' setiap bulan', 'tone' => ($setting?->is_active ?? true) ? 'success' : 'neutral'],
            ],
        ]);
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_day' => ['required', 'integer', 'min:1', 'max:28'],
            'invoice_time' => ['required', 'date_format:H:i'],
            'default_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'is_active' => ['required', 'boolean'],
        ]);

        $setting = BillingSetting::query()->updateOrCreate(
            ['name' => 'monthly_tuition'],
            [
                'invoice_day' => $validated['invoice_day'],
                'invoice_time' => $validated['invoice_time'].':00',
                'default_amount' => $validated['default_amount'],
                'is_active' => $validated['is_active'],
            ],
        );

        ActivityLogger::log($request, 'billing.schedule.updated', 'finance', 'Updated monthly billing schedule', $setting);

        return to_route('admin.billing-settings.index')->with('status', 'Jadwal dan tarif default berhasil diperbarui.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $validated = $this->validatedRule($request);
        $this->assertRuleConsistency($validated);
        $rule = BillingRule::query()->create($validated);

        ActivityLogger::log($request, 'billing.rule.created', 'finance', 'Created billing rule', $rule, [
            'scope' => $rule->scopeLabel(),
            'charge_kind' => $rule->charge_kind,
        ]);

        return back()->with('status', 'Aturan tagihan berhasil dibuat.');
    }

    public function updateRule(Request $request, BillingRule $billingRule): RedirectResponse
    {
        $validated = $this->validatedRule($request);
        $this->assertRuleConsistency($validated, $billingRule);
        $billingRule->update($validated);

        ActivityLogger::log($request, 'billing.rule.updated', 'finance', 'Updated billing rule', $billingRule, [
            'scope' => $billingRule->fresh(['branch', 'group'])->scopeLabel(),
            'charge_kind' => $billingRule->charge_kind,
        ]);

        return back()->with('status', 'Aturan tagihan berhasil diperbarui.');
    }

    public function destroyRule(Request $request, BillingRule $billingRule): RedirectResponse
    {
        $billingRule->update(['is_active' => false]);
        $billingRule->delete();

        ActivityLogger::log($request, 'billing.rule.deleted', 'finance', 'Archived billing rule', $billingRule);

        return back()->with('status', 'Aturan tagihan diarsipkan. Tagihan yang sudah terbit tetap tersimpan.');
    }

    public function generateMonthly(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $month = filled($validated['month'] ?? null)
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth();
        $result = $this->generator->generateMonthly($month);

        ActivityLogger::log($request, 'billing.monthly.generated', 'finance', 'Generated monthly tuition invoices', null, [
            'month' => $month->format('Y-m'),
            ...$result,
        ]);

        return back()->with('status', "Tagihan bulanan dibuat: {$result['created']}; dilewati karena sudah ada: {$result['skipped']}.");
    }

    public function generateOneTime(Request $request, BillingRule $billingRule): RedirectResponse
    {
        $validated = $request->validate([
            'issue_date' => ['required', 'date'],
        ]);
        $result = $this->generator->generateOneTime($billingRule, Carbon::parse($validated['issue_date']));

        ActivityLogger::log($request, 'billing.one-time.generated', 'finance', 'Generated one-time invoices', $billingRule, [
            'issue_date' => $validated['issue_date'],
            ...$result,
        ]);

        return back()->with('status', "Tagihan satu kali dibuat: {$result['created']}; dilewati karena sudah ada: {$result['skipped']}.");
    }

    private function validatedRule(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'charge_kind' => ['required', Rule::in([BillingRule::KIND_MONTHLY, BillingRule::KIND_ONE_TIME])],
            'payment_type' => ['required', Rule::in(BillingRule::PAYMENT_TYPES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'branch_id')->whereNull('deleted_at')],
            'group_id' => ['nullable', 'integer', Rule::exists('class_groups', 'group_id')->whereNull('deleted_at')],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['charge_kind'] === BillingRule::KIND_MONTHLY) {
            $validated['payment_type'] = 'TUITION';
        }

        return $validated;
    }

    private function assertRuleConsistency(array $validated, ?BillingRule $ignore = null): void
    {
        if (
            $validated['charge_kind'] === BillingRule::KIND_MONTHLY
            && empty($validated['branch_id'])
            && empty($validated['group_id'])
        ) {
            throw ValidationException::withMessages([
                'branch_id' => 'Tarif SPP umum sudah diatur melalui Tarif default. Pilih cabang atau kelas untuk membuat tarif khusus.',
            ]);
        }

        if (! empty($validated['branch_id']) && ! empty($validated['group_id'])) {
            $groupBelongsToBranch = Group::query()
                ->whereKey($validated['group_id'])
                ->where('branch_id', $validated['branch_id'])
                ->exists();

            if (! $groupBelongsToBranch) {
                throw ValidationException::withMessages([
                    'group_id' => 'Kelas latihan harus berada pada cabang yang dipilih.',
                ]);
            }
        }

        if ($validated['charge_kind'] !== BillingRule::KIND_MONTHLY || ! $validated['is_active']) {
            return;
        }

        $existingRules = BillingRule::query()
            ->where('charge_kind', BillingRule::KIND_MONTHLY)
            ->where('is_active', true)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->when(
                empty($validated['branch_id']),
                fn ($query) => $query->whereNull('branch_id'),
                fn ($query) => $query->where('branch_id', $validated['branch_id']),
            )
            ->when(
                empty($validated['group_id']),
                fn ($query) => $query->whereNull('group_id'),
                fn ($query) => $query->where('group_id', $validated['group_id']),
            )
            ->get();

        $incomingStart = filled($validated['effective_from'] ?? null) ? Carbon::parse($validated['effective_from']) : Carbon::create(1900, 1, 1);
        $incomingEnd = filled($validated['effective_until'] ?? null) ? Carbon::parse($validated['effective_until']) : Carbon::create(9999, 12, 31);

        foreach ($existingRules as $existing) {
            $existingStart = $existing->effective_from ?? Carbon::create(1900, 1, 1);
            $existingEnd = $existing->effective_until ?? Carbon::create(9999, 12, 31);

            if ($incomingStart->lte($existingEnd) && $existingStart->lte($incomingEnd)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Periode ini bertumpang tindih dengan aturan bulanan aktif pada cakupan yang sama.',
                ]);
            }
        }
    }

    private function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
