<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Letters\Letter;
use App\Models\Letters\LetterRequest;
use App\Support\Letters\LetterTemplates;

/**
 * HR & General → Letter Templates: the entry of the hub, and its Dashboard
 * tab (slug general.letters.dashboard) — a view-only summary.
 */
class LetterDashboardController extends Controller
{
    /** The tabs of the hub, in order: [slug, route]. */
    public const TABS = [
        ['general.letters.dashboard', 'general.letters.dashboard'],
        ['general.letters.requests', 'general.letters.requests.index'],
        ['general.letters.register', 'general.letters.register.index'],
        ['general.letters.compose', 'general.letters.compose.index'],
        ['general.letter-templates', 'general.letters.settings.index'],
    ];

    /** The sidebar link: opens the first tab the person can view, so nobody lands on a refused page. */
    public function home()
    {
        $employee = Employee::find(session('user.id'));

        foreach (self::TABS as [$slug, $route]) {
            if ($employee?->canAccessMenu($slug)) {
                return redirect()->route($route);
            }
        }

        return redirect()->route('dashboard')->with('warning', 'You have no access to any tab of Letter Templates.');
    }

    public function index()
    {
        $now = now();
        $active = fn () => Letter::query()->where('status', '!=', 'void');

        $kpis = [
            ['label' => 'Outgoing this month', 'value' => $active()->where('direction', Letter::DIRECTION_OUTGOING)
                ->whereYear('letter_date', $now->year)->whereMonth('letter_date', $now->month)->count(), 'icon' => 'paper-plane', 'tone' => 'blue'],
            ['label' => 'Incoming this month', 'value' => $active()->where('direction', Letter::DIRECTION_INCOMING)
                ->whereYear('letter_date', $now->year)->whereMonth('letter_date', $now->month)->count(), 'icon' => 'inbox', 'tone' => 'green'],
            ['label' => 'Pending requests', 'value' => LetterRequest::where('status', LetterRequest::PENDING)->count(), 'icon' => 'hourglass-half', 'tone' => 'amber'],
            ['label' => 'Requests due ≤ 3 days', 'value' => LetterRequest::open()->whereNotNull('needed_by')
                ->whereDate('needed_by', '<=', $now->copy()->addDays(3))->count(), 'icon' => 'triangle-exclamation', 'tone' => 'red'],
            ['label' => 'Voided this year', 'value' => Letter::where('status', 'void')->whereYear('voided_at', $now->year)->count(), 'icon' => 'ban', 'tone' => 'gray'],
        ];

        // Letters generated this year, per template.
        $byType = Letter::query()->where('status', '!=', 'void')
            ->whereIn('source', [Letter::SOURCE_TEMPLATE, Letter::SOURCE_CUSTOM, Letter::SOURCE_OFFERING])
            ->whereYear('letter_date', $now->year)
            ->selectRaw("CASE WHEN source = 'template' THEN template_key ELSE source END as type_key, COUNT(*) as total")
            ->groupBy('type_key')
            ->pluck('total', 'type_key');

        $typeLabels = ['offering_letter' => 'Offering Letter', 'custom' => 'Custom Letter'] + LetterTemplates::labels();

        return view('hr-general.letters.dashboard', [
            'kpis'          => $kpis,
            'oldestPending' => LetterRequest::with('employee.basicData')->open()->orderBy('created_at')->limit(5)->get(),
            'recent'        => Letter::with('code')->orderByDesc('letter_date')->orderByDesc('id')->limit(10)->get(),
            'byType'        => collect($typeLabels)->map(fn ($label, $key) => ['label' => $label, 'total' => (int) ($byType[$key] ?? 0)])
                ->sortByDesc('total')->values(),
            'year'          => $now->year,
        ]);
    }
}
