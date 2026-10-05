@extends('dashboard')
@php
    use App\Models\Letters\Letter;

    $incoming = $letter->direction === Letter::DIRECTION_INCOMING;
    $editing = $letter->exists;
    // A generated or offering letter: its content belongs to the tab that wrote it; here only how it went out and its final file.
    $deliveryOnly = $editing && !$letter->isManual();
    $title = $deliveryOnly ? 'Delivery Details & Final File' : ($editing ? 'Edit ' : 'Log ') . ($incoming ? 'Incoming Letter' : 'Outgoing Letter');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-gray-50 disabled:text-gray-500';
    $file = 'block w-full text-xs text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border file:border-gray-200 file:bg-white file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-50';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $help = 'text-[11px] text-gray-400 mt-1';
    $value = fn (string $name, $default = null) => old($name, $letter->{$name} ?? $default);
    $dateValue = fn (string $name) => old($name, $letter->{$name}?->toDateString());
@endphp
@section('title', $title)
@section('page-title', 'Letter Templates')
@section('page-subtitle', $title)

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.letters.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    <form method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('general.letters.register.update', $letter) : route('general.letters.register.store') }}"
        class="bg-white rounded-xl border border-gray-200 shadow-sm">
        @csrf
        <input type="hidden" name="direction" value="{{ $letter->direction }}">

        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">{{ $title }}</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    @if($deliveryOnly)
                        {{ $letter->typeLabel() }} {{ $letter->letter_number }} — its content is changed where it was written. Here: how it went out, and the signed / stamped final file.
                    @elseif($incoming)
                        A letter the company received. It gets the next agenda number of the incoming register; the letter number is the sender's own.
                    @else
                        A letter written outside the system. Leave the number empty to give it the next number of the shared outgoing counter.
                    @endif
                </p>
            </div>
            <a href="{{ route('general.letters.register.index', ['direction' => $letter->direction]) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700 whitespace-nowrap">
                <i class="fas fa-arrow-left text-[10px]"></i> Back to the register
            </a>
        </div>

        <div class="px-5 py-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            @unless($deliveryOnly)
                @if($incoming)
                    @if($editing)
                        <div>
                            <span class="{{ $label }}">Agenda Number</span>
                            <p class="text-sm font-semibold text-gray-800 py-2">{{ $letter->agenda_number }}</p>
                        </div>
                    @endif
                    <div>
                        <label for="letterNumber" class="{{ $label }}">Sender's Letter Number</label>
                        <input type="text" name="letter_number" id="letterNumber" maxlength="100" value="{{ $value('letter_number') }}" class="{{ $input }}">
                    </div>
                    <div class="md:col-span-2">
                        <label for="counterparty" class="{{ $label }}">Sender <span class="text-red-500">*</span></label>
                        <input type="text" name="counterparty" id="counterparty" required maxlength="255" value="{{ $value('counterparty') }}" class="{{ $input }}">
                    </div>
                @else
                    <div>
                        <label for="letterNumber" class="{{ $label }}">Letter Number</label>
                        <input type="text" name="letter_number" id="letterNumber" maxlength="100" value="{{ $value('letter_number') }}" class="{{ $input }}"
                            placeholder="{{ $editing ? '' : 'Empty: generated from the outgoing format' }}">
                        <p class="{{ $help }}">{{ $editing ? 'Emptied, it keeps the number it has.' : 'Typed by hand, it does not use up a running number.' }}</p>
                    </div>
                    <div>
                        <span class="{{ $label }}">Language <span class="text-red-500">*</span></span>
                        @include('hr-general.recruitment.components.language-switch', [
                            'switchName' => 'language', 'switchId' => 'registerLanguage', 'languages' => $languages,
                            'switchValue' => $value('language', 'id'),
                        ])
                        <p class="{{ $help }}">Prints the IN / EN segment of a generated number.</p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="counterparty" class="{{ $label }}">Recipient <span class="text-red-500">*</span></label>
                        <input type="text" name="counterparty" id="counterparty" required maxlength="255" value="{{ $value('counterparty') }}" class="{{ $input }}">
                    </div>
                @endif

                <div class="md:col-span-2">
                    <label for="subject" class="{{ $label }}">Subject <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" id="subject" required maxlength="255" value="{{ $value('subject') }}" class="{{ $input }}">
                </div>

                <div>
                    <label for="letterDate" class="{{ $label }}">Letter Date <span class="text-red-500">*</span></label>
                    <input type="date" name="letter_date" id="letterDate" required value="{{ $dateValue('letter_date') }}" class="{{ $input }}">
                </div>
                @if($incoming)
                    <div>
                        <label for="receivedDate" class="{{ $label }}">Received On <span class="text-red-500">*</span></label>
                        <input type="date" name="received_date" id="receivedDate" required value="{{ $dateValue('received_date') ?? now()->toDateString() }}" class="{{ $input }}">
                    </div>
                @endif
                <div>
                    <label for="letterCode" class="{{ $label }}">{{ $incoming ? 'Classification' : 'Letter Code' }}</label>
                    <select name="letter_code_id" id="letterCode" class="{{ $input }}">
                        <option value="">-- None --</option>
                        @foreach($codes as $code)
                            <option value="{{ $code->id }}" @selected((string) $value('letter_code_id') === (string) $code->id)>{{ $code->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless

            @unless($incoming)
                <div>
                    <label for="deliveredVia" class="{{ $label }}">Received By / Delivered Via</label>
                    <input type="text" name="delivered_via" id="deliveredVia" maxlength="150" value="{{ $value('delivered_via') }}" class="{{ $input }}"
                        placeholder="e.g. front desk, courier, email">
                </div>
                <div>
                    <label for="receiptNumber" class="{{ $label }}">Receipt Number</label>
                    <input type="text" name="receipt_number" id="receiptNumber" maxlength="150" value="{{ $value('receipt_number') }}" class="{{ $input }}">
                </div>
            @endunless

            <div class="md:col-span-2">
                <label for="file" class="{{ $label }}">{{ $incoming ? 'Scan of the Letter' : 'Final File (signed / stamped)' }}</label>
                <input type="file" name="file" id="file" accept=".pdf,.jpg,.jpeg,.png{{ $deliveryOnly ? '' : ',.doc,.docx' }}" class="{{ $file }}">
                <p class="{{ $help }}">
                    PDF or image, up to 20 MB.
                    @if($letter->finalPath())
                        Current: <a href="{{ route('general.letters.file', $letter) }}" target="_blank" class="font-semibold" style="color: var(--primary-color);">{{ $letter->final_name ?: 'file' }}</a> — choose a file only to replace it.
                    @endif
                </p>
            </div>

            <div class="md:col-span-2">
                <label for="notes" class="{{ $label }}">Notes</label>
                <textarea name="notes" id="notes" rows="3" maxlength="2000" class="{{ $input }}">{{ $value('notes') }}</textarea>
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
            <a href="{{ route('general.letters.register.index', ['direction' => $letter->direction]) }}" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</a>
            <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                {{ $editing ? 'Save' : ($incoming ? 'Log Incoming Letter' : 'Log Outgoing Letter') }}
            </button>
        </div>
    </form>
</div>
@endsection
