{{-- Kepada + referensi kontrak (hanya bila diisi). --}}
@if(!empty($f['addressed_to']) || !empty($f['contract_no']))
    <table class="details" style="margin-left:0">
        @if(!empty($f['addressed_to']))
            <tr><td class="label">{{ $lang === 'en' ? 'To' : 'Kepada' }}</td><td class="colon">:</td><td>{{ $f['addressed_to'] }}</td></tr>
        @endif
        @if(!empty($f['contract_no']))
            <tr><td class="label">{{ $lang === 'en' ? 'Contract ref.' : 'Ref. kontrak' }}</td><td class="colon">:</td><td>{{ $f['contract_no'] }}</td></tr>
        @endif
    </table>
@endif
