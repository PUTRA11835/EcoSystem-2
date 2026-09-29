@extends('dashboard')

@section('title', 'Two-Factor Enforcement')
@section('page-title', 'Two-Factor Enforcement')
@section('page-subtitle', 'Choose which roles are required to enable 2FA before using the app')

@section('content')
<div class="space-y-6">

    {{-- Flash session sengaja TIDAK dirender di sini: dashboard.blade.php sudah
         menampilkannya lewat showToast(). Merendernya ulang membuat toast dobel. --}}
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <i class="fas fa-circle-exclamation mt-0.5 text-red-500"></i>
            <ul class="list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3">
        <i class="fas fa-circle-info mt-0.5 text-xs text-indigo-500"></i>
        <p class="text-xs leading-relaxed text-indigo-900">
            Anyone holding a <strong>checked</strong> role below is redirected to Settings and must finish 2FA setup
            before they can use the rest of the app — this is re-checked on every request, so it also catches
            employees who are already logged in. Uncheck a role to make 2FA optional (self-enrolled) again for that
            role. Uncheck every role to turn mandatory 2FA off entirely. Changes apply immediately.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.two-factor-enforcement.update') }}" class="space-y-6">
        @csrf

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600">
                    <i class="fas fa-shield-halved text-sm"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-gray-900">Mandatory 2FA by role</h2>
                    <p class="text-xs text-gray-500">Checked roles cannot skip 2FA setup.</p>
                </div>
            </header>

            <div class="p-2 max-h-[28rem] overflow-y-auto divide-y divide-gray-50">
                @foreach($roles as $role)
                    <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition-colors">
                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}"
                            {{ in_array((int) $role->id, $enforcedRoleIds, true) ? 'checked' : '' }}
                            class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-400">
                        <span class="text-sm text-gray-700">{{ $role->name }}</span>
                    </label>
                @endforeach
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-md hover:bg-red-900 transition-colors">
                    <i class="fas fa-floppy-disk mr-1.5"></i>Save
                </button>
            </div>
        </section>
    </form>
</div>
@endsection
