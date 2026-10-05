{{-- "Next step" link at the bottom of a Settings step. Parameters: $next (step key), $nextLabel. --}}
<div class="mt-4 flex justify-end">
    <button type="button" onclick="showSettingsStep('{{ $next }}')"
        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg border bg-white hover:bg-indigo-50" style="color: var(--primary-color); border-color: var(--primary-color);">
        Next: {{ $nextLabel }} <i class="fas fa-arrow-right text-[10px]"></i>
    </button>
</div>
