<?php

namespace App\Http\Controllers;

use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /** Nilai default preferensi — satu-satunya sumber kebenaran. */
    const DEFAULT_PREFERENCES = [
        'theme'                 => 'light',
        'primary_color'         => '#991b1b',
        'sidebar_style'         => 'gradient',
        'font_size'             => 'medium',
        'compact_mode'          => false,
        'show_animations'       => true,
        'language'              => 'en',
        'timezone'              => 'Asia/Jakarta',
        'date_format'           => 'DD/MM/YYYY',
        'notifications_enabled' => true,
        'email_notifications'   => true,
        'push_notifications'    => false,
    ];

    /** Nilai yang diperbolehkan untuk field bertipe pilihan (whitelist). */
    const ALLOWED_VALUES = [
        'theme'         => ['light', 'dark', 'auto'],
        'sidebar_style' => ['gradient', 'solid'],
        'font_size'     => ['small', 'medium', 'large'],
        'language'      => ['en', 'id'],
        'timezone'      => ['Asia/Jakarta', 'Asia/Singapore', 'UTC'],
        'date_format'   => ['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD'],
    ];

    const PROFILE_SECTIONS = [
        'basic_data'     => 'Basic Data',
        'address'        => 'Address',
        'identification' => 'Identification',
        'family'         => 'Family',
        'education'      => 'Education',
        'qualification'  => 'Qualification',
        'contract'       => 'Contract',
        'bank'           => 'Bank Account',
        'payment'        => 'Basic Payment',
        'attachment'     => 'Attachment',
    ];

    public function index(Request $request)
    {
        try {
            $user = session('user');
            $token = session('auth_token');

            if (!$user || !$token) {
                return redirect()->route('login')->withErrors(['message' => 'Please login first']);
            }

            // Muat preferensi dari DB (sumber persisten), lengkapi dengan default
            // untuk field yang belum tersimpan, lalu sinkronkan ke session agar
            // dashboard/view lain langsung memakai nilai yang sama.
            $userPreferences = $this->loadPreferences();
            session(['user_preferences' => $userPreferences]);

            Log::info('Settings page accessed', [
                'user_id' => $user['id'],
                'user_name' => $user['name'] ?? $user['company_name'] ?? 'Unknown',
            ]);

            return view('settings.index', [
                'user'        => $user,
                'preferences' => $userPreferences,
            ]);

        } catch (\Exception $e) {
            Log::error('Settings page error', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return redirect()->route('dashboard')->withErrors([
                'message' => 'An error occurred while loading settings.'
            ]);
        }
    }

    public function updatePreferences(Request $request)
    {
        try {
            $user = session('user');
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Whitelist + validasi: gabungkan input ke atas nilai tersimpan saat ini,
            // buang key asing, dan tolak nilai enum yang tidak dikenal.
            $current  = $this->loadPreferences();
            $incoming = $this->sanitizePreferences($request->all());
            $preferences = array_merge($current, $incoming);

            $this->persistPreferences($preferences);

            Log::info('User preferences updated', [
                'user_id' => $user['id'],
                'preferences' => $preferences,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully!',
                'preferences' => $preferences,
            ]);

        } catch (\Exception $e) {
            Log::error('Update preferences error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings'
            ], 500);
        }
    }

    public function resetPreferences(Request $request)
    {
        try {
            $user = session('user');
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Reset ke default & simpan (DB + session)
            $defaultPreferences = self::DEFAULT_PREFERENCES;
            $this->persistPreferences($defaultPreferences);

            Log::info('User preferences reset to default', [
                'user_id' => $user['id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings reset to default successfully!',
                'preferences' => $defaultPreferences,
            ]);

        } catch (\Exception $e) {
            Log::error('Reset preferences error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings'
            ], 500);
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Muat preferensi user yang terpersist di DB (auth_users.preferences),
     * dilengkapi default untuk field yang belum ada. Selalu mengembalikan set
     * lengkap. Bila baris auth_user tak ditemukan, kembalikan default penuh.
     */
    private function loadPreferences(): array
    {
        $authUser = $this->resolveAuthUser();
        $stored   = ($authUser && is_array($authUser->preferences)) ? $authUser->preferences : [];

        // Default di bawah, nilai tersimpan menimpa; key asing dibuang.
        return array_merge(self::DEFAULT_PREFERENCES, array_intersect_key($stored, self::DEFAULT_PREFERENCES));
    }

    /**
     * Simpan set preferensi lengkap ke DB (bila auth_user ditemukan) dan session.
     */
    private function persistPreferences(array $preferences): void
    {
        $authUser = $this->resolveAuthUser();
        if ($authUser) {
            $authUser->preferences = $preferences;
            $authUser->save();
        }

        session(['user_preferences' => $preferences]);
    }

    /**
     * Bersihkan payload dari client: hanya key yang dikenal yang dipertahankan,
     * boolean dinormalkan, dan field enum divalidasi terhadap whitelist
     * (nilai tak dikenal diabaikan sehingga nilai lama tetap dipakai).
     */
    private function sanitizePreferences(array $input): array
    {
        $clean = [];

        foreach (self::DEFAULT_PREFERENCES as $key => $default) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];

            if (is_bool($default)) {
                $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (isset(self::ALLOWED_VALUES[$key])) {
                if (in_array($value, self::ALLOWED_VALUES[$key], true)) {
                    $clean[$key] = $value;
                }
            } elseif ($key === 'primary_color') {
                if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                    $clean[$key] = strtolower($value);
                }
            } else {
                $clean[$key] = is_scalar($value) ? (string) $value : $default;
            }
        }

        return $clean;
    }

    /**
     * Resolve baris AuthUser untuk user yang sedang login dari session('user').
     * EcoSystem hanya untuk employee, tapi resolver ini tetap menangani customer.
     */
    private function resolveAuthUser(): ?AuthUser
    {
        $user = session('user');
        if (!$user || empty($user['id'])) {
            return null;
        }

        $type = $user['type'] ?? 'employee';

        return $type === 'customer'
            ? AuthUser::where('customer_id', $user['id'])->first()
            : AuthUser::where('employee_id', $user['id'])->first();
    }
}