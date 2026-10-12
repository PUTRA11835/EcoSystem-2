<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use App\Models\AppConfig;
use App\Models\AuditLog;
use App\Support\AiModelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Control Center → AI Usage.
 *
 * Pemakaian token dibaca dari ai_usage_logs (ditulis AiUsageRecorder dari angka
 * usage yang dikembalikan provider). Biaya adalah ESTIMASI dari harga katalog.
 *
 * "Sisa saldo" TIDAK bisa dibaca dari provider: Anthropic dan OpenAI tidak
 * menyediakan API saldo prabayar. Jadi admin mengisi nominal saldo + tanggal
 * mulai hitung (biasanya saat top-up), dan sisanya = saldo - estimasi biaya
 * sejak tanggal itu. Angka resmi tetap ada di console masing-masing provider.
 */
class AiUsageController extends Controller
{
    public const BALANCE_KEY = 'ai.balance';

    private const PROVIDERS = [
        'anthropic' => 'Claude (Anthropic)',
        'openai' => 'OpenAI GPT',
    ];

    private const SOURCES = [
        'chat' => 'Chat (AI Assistant / Ticket Q&A)',
        'research' => 'AI Research & AI Summarize',
        'ticket_analysis' => 'Ticket Analyzer',
        'report' => 'Word Report Generator',
    ];

    private const RANGES = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'month' => 'This month',
        'all' => 'All time',
    ];

    public function index(Request $request)
    {
        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '30d';
        $from = $this->rangeStart($range);

        $base = fn () => AiUsageLog::query()->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        $sums = 'COUNT(*) AS calls, SUM(input_tokens) AS input_tokens, SUM(output_tokens) AS output_tokens, '
            . 'SUM(cache_read_tokens) AS cache_read_tokens, SUM(cache_write_tokens) AS cache_write_tokens, '
            . 'SUM(cost_usd) AS cost_usd';

        $totals = $base()->selectRaw($sums)->first();

        $byModel = $base()->selectRaw("provider, model, {$sums}")
            ->groupBy('provider', 'model')->orderByDesc('cost_usd')->get();

        $bySource = $base()->selectRaw("source, {$sums}")
            ->groupBy('source')->orderByDesc('cost_usd')->get();

        $byUser = $base()->whereNotNull('employee_id')
            ->selectRaw("employee_id, {$sums}")
            ->groupBy('employee_id')->orderByDesc('cost_usd')->limit(10)->get();

        $names = DB::table('employee_basic_data')
            ->whereIn('employee_id', $byUser->pluck('employee_id'))
            ->get(['employee_id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($r) => [$r->employee_id => trim($r->first_name . ' ' . $r->last_name)]);

        $daily = $base()->selectRaw('DATE(created_at) AS day, SUM(cost_usd) AS cost_usd, '
                . 'SUM(input_tokens + cache_read_tokens + cache_write_tokens + output_tokens) AS tokens')
            ->groupBy('day')->orderBy('day')->get();

        $recent = AiUsageLog::query()->latest('id')->limit(25)->get();

        return view('admin.ai-usage', [
            'ranges' => self::RANGES,
            'range' => $range,
            'providers' => self::PROVIDERS,
            'sources' => self::SOURCES,
            'catalog' => AiModelSettings::catalog(),
            'totals' => $totals,
            'byModel' => $byModel,
            'bySource' => $bySource,
            'byUser' => $byUser,
            'names' => $names,
            'daily' => $daily,
            'recent' => $recent,
            'trackingSince' => AiUsageLog::min('created_at'),
            'balances' => $this->balances(),
        ]);
    }

    public function saveBalance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => 'required|in:' . implode(',', array_keys(self::PROVIDERS)),
            'balance_usd' => 'required|numeric|min:0|max:10000000',
            'since' => 'required|date|before_or_equal:today',
            'low_threshold_usd' => 'nullable|numeric|min:0|max:10000000',
        ]);

        $stored = AppConfig::getJson(self::BALANCE_KEY, []);
        $before = $stored[$data['provider']] ?? null;

        $stored[$data['provider']] = [
            'balance_usd' => round((float) $data['balance_usd'], 2),
            'since' => Carbon::parse($data['since'])->startOfDay()->toDateTimeString(),
            'low_threshold_usd' => isset($data['low_threshold_usd']) ? round((float) $data['low_threshold_usd'], 2) : null,
        ];

        AppConfig::setJson(self::BALANCE_KEY, $stored, 'Saldo AI per provider (Control Center → AI Usage)');

        $configId = AppConfig::where('key', self::BALANCE_KEY)->value('id') ?? 0;

        AuditLog::recordAction(
            module: 'AI Usage',
            auditableType: 'AppConfig',
            auditableId: $configId,
            event: 'updated',
            recordLabel: 'AI balance (' . self::PROVIDERS[$data['provider']] . ')',
            description: 'updated AI balance for ' . self::PROVIDERS[$data['provider']],
            old: $before,
            new: $stored[$data['provider']],
        );

        return redirect()
            ->route('admin.ai-usage', $request->only('range'))
            ->with('success', self::PROVIDERS[$data['provider']] . ' balance saved.');
    }

    /**
     * @return array<string, array{balance_usd: ?float, since: ?string, spent: float, remaining: ?float, low_threshold_usd: ?float, is_low: bool}>
     */
    private function balances(): array
    {
        $stored = AppConfig::getJson(self::BALANCE_KEY, []);
        $out = [];

        foreach (array_keys(self::PROVIDERS) as $provider) {
            $cfg = is_array($stored[$provider] ?? null) ? $stored[$provider] : null;

            if (null === $cfg) {
                $out[$provider] = [
                    'balance_usd' => null, 'since' => null, 'spent' => 0.0,
                    'remaining' => null, 'low_threshold_usd' => null, 'is_low' => false,
                ];

                continue;
            }

            $spent = (float) AiUsageLog::where('provider', $provider)
                ->where('created_at', '>=', $cfg['since'])
                ->sum('cost_usd');

            $remaining = (float) $cfg['balance_usd'] - $spent;
            $threshold = $cfg['low_threshold_usd'] ?? null;

            $out[$provider] = [
                'balance_usd' => (float) $cfg['balance_usd'],
                'since' => $cfg['since'],
                'spent' => $spent,
                'remaining' => $remaining,
                'low_threshold_usd' => $threshold,
                'is_low' => null !== $threshold && $remaining <= $threshold,
            ];
        }

        return $out;
    }

    private function rangeStart(string $range): ?Carbon
    {
        return match ($range) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            '30d' => now()->subDays(29)->startOfDay(),
            'month' => now()->startOfMonth(),
            default => null,
        };
    }
}
