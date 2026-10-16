{{--
    The signing record of a contract (DomPDF): who did what and when, the fingerprint of the stamped file that was signed, and the
    signatures that were applied. Given by ContractSigningController::recordData(). It is evidence next to the contract, not the contract.
--}}
@php
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d M Y H:i') : '—';
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d M Y') : '—';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Signing record {{ $contract->contract_number }}</title>
    <style>
        @page { margin: 36pt 40pt; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 2pt; }
        h2 { font-size: 11pt; margin: 16pt 0 5pt; border-bottom: 1px solid #999; padding-bottom: 2pt; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 4pt 5pt; border-bottom: 1px solid #ddd; }
        th { font-size: 8.5pt; text-transform: uppercase; color: #555; }
        .k { width: 28%; color: #555; }
        .sig { height: 42pt; }
        .mono { font-family: "Courier New", monospace; font-size: 8.5pt; word-break: break-all; }
    </style>
</head>
<body>
    <h1>Signing record</h1>
    <p class="muted">{{ $contract->contract_number }} · {{ $contract->contract_name }}</p>

    <h2>Contract</h2>
    <table>
        <tr><td class="k">Employee</td><td>{{ $employeeName ?? '—' }}</td></tr>
        <tr><td class="k">Type / position</td><td>{{ $contract->contract_type }} · {{ $contract->position ?: '—' }}</td></tr>
        <tr><td class="k">Period</td><td>{{ $d($contract->start_date) }} – {{ $contract->end_date ? $d($contract->end_date) : 'Permanent' }}</td></tr>
        <tr><td class="k">Company signatory</td><td>{{ $contract->signatory_name ?: '—' }}{{ $contract->signatory_title ? ' — ' . $contract->signatory_title : '' }}</td></tr>
        <tr><td class="k">Approver</td><td>{{ $contract->approver_name ?: '—' }}{{ $contract->approver_title ? ' — ' . $contract->approver_title : '' }}</td></tr>
        <tr><td class="k">Status</td><td>{{ $stageLabel }}</td></tr>
        <tr><td class="k">Stamped file (SHA-256)</td><td class="mono">{{ $contract->sign_file_hash ?: '—' }}</td></tr>
    </table>

    <h2>Signatures</h2>
    <table>
        <tr>
            <td style="width:50%">
                <div class="muted">Employee</div>
                @if(!empty($signatures['employee']))<img class="sig" src="{{ $signatures['employee'] }}" alt="">@else<div class="sig"></div>@endif
                <div><strong>{{ $employeeName ?? '—' }}</strong></div>
                <div class="muted">{{ $contract->employee_signed_at ? 'Signed ' . $dt($contract->employee_signed_at) : 'Not signed yet' }}</div>
            </td>
            <td style="width:50%">
                <div class="muted">Approver</div>
                @if(!empty($signatures['approver']))<img class="sig" src="{{ $signatures['approver'] }}" alt="">@else<div class="sig"></div>@endif
                <div><strong>{{ $contract->approver_name ?: '—' }}</strong></div>
                <div class="muted">{{ $contract->approver_signed_at ? 'Signed ' . $dt($contract->approver_signed_at) : 'Not signed yet' }}</div>
            </td>
        </tr>
    </table>

    <h2>History</h2>
    <table>
        <thead><tr><th style="width:24%">When</th><th style="width:30%">Step</th><th style="width:22%">By</th><th>Note</th></tr></thead>
        <tbody>
            @forelse($events as $e)
                <tr>
                    <td>{{ $dt($e->created_at) }}</td>
                    <td>{{ $labels[$e->event] ?? $e->event }}</td>
                    <td>{{ $e->actor_name ?: '—' }}</td>
                    <td>
                        {{ $e->note }}
                        @if(isset($e->meta['overlay']) && $e->meta['overlay'] === false)
                            <div class="muted">Signature recorded here; it could not be drawn on the PDF{{ !empty($e->meta['reason']) ? ': ' . $e->meta['reason'] : '' }}.</div>
                        @elseif(isset($e->meta['pages']) && $e->meta['pages'])
                            <div class="muted">Signature drawn on all {{ $e->meta['pages'] }} pages.</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Nothing recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
