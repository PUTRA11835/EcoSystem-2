<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordSetupController extends Controller
{
    // =========================================================================
    // PAGES — PASSWORD SETUP (akun baru)
    // =========================================================================

    /**
     * Halaman "Silakan cek email Anda".
     * type=setup  → akun baru (default)
     * type=reset  → forgot password
     */
    public function showCheckEmail(Request $request)
    {
        $email = $request->query('email', '');
        $type  = $request->query('type', 'setup'); // 'setup' | 'reset'
        return view('auth.check-email', compact('email', 'type'));
    }

    /**
     * Halaman form atur/reset password — ditampilkan saat user klik link di email.
     */
    public function showChangePassword(Request $request)
    {
        $token = $request->query('token', '');

        if (empty($token)) {
            return redirect()->route('login')->with('error', 'Invalid link.');
        }

        // Token dibandingkan sebagai hash (token di URL = plaintext, DB = sha256 hash)
        $authUser = DB::table('auth_users')
            ->where('cp_token', hash('sha256', $token))
            ->where('cp_token_expires_at', '>', now())
            ->first();

        if (!$authUser) {
            return redirect()->route('login')
                ->with('error', 'This link has expired. Please request a new one.');
        }

        return view('auth.change-password', compact('token'));
    }

    /**
     * Proses simpan password baru (digunakan untuk setup akun baru & reset password).
     */
    public function submitChangePassword(Request $request)
    {
        $request->validate([
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        // Token di URL adalah plaintext; DB menyimpan sha256 hash
        $authUser = DB::table('auth_users')
            ->where('cp_token', hash('sha256', $request->token))
            ->where('cp_token_expires_at', '>', now())
            ->first();

        if (!$authUser) {
            return back()->withErrors([
                'token' => 'This link has expired. Please request a new one.',
            ]);
        }

        DB::table('auth_users')->where('id', $authUser->id)->update([
            'password'            => Hash::make($request->password),
            'is_already_cp'       => true,
            'cp_token'            => null,
            'cp_token_expires_at' => null,
            'updated_at'          => now(),
        ]);

        Log::info('PasswordSetupController: password berhasil diubah', [
            'auth_user_id' => $authUser->id,
        ]);

        // Customers are redirected to Jarvies login, employees to EcoSystem login
        $isCustomer = ($authUser->user_type ?? '') === 'customer' || !empty($authUser->customer_id);
        if ($isCustomer) {
            // Gunakan config() bukan env() agar berfungsi saat config:cache
            $jarviesBase  = rtrim(config('services.jarvies.url', config('app.url')), '/');
            $jarviesLogin = $jarviesBase . '/login';
            return redirect($jarviesLogin)
                ->with('success', 'Password set successfully. You can now log in to Jarvies with your new password.');
        }

        return redirect()->route('login')
            ->with('success', 'Password set successfully. You can now log in with your new password.');
    }

    // =========================================================================
    // PAGES — FORGOT PASSWORD
    // =========================================================================

    /**
     * Halaman form forgot password — user masukkan email terdaftar.
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Proses forgot password:
     * - Cari auth_user berdasarkan email
     * - Generate token & kirim email reset
     * - Redirect ke halaman "cek email"
     */
    public function submitForgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $authUser = DB::table('auth_users')
            ->where('email', $request->email)
            ->where('is_active', true)
            ->first();

        // Selalu tampilkan pesan sukses agar tidak mengekspos info akun (security)
        if (!$authUser) {
            $maskedEmail = $this->maskEmail($request->email);
            return redirect()->route('password-setup.check-email', [
                'email' => $maskedEmail,
                'type'  => 'reset',
            ]);
        }

        self::generateAndSendToken($authUser, 'reset');

        [$local, $domain] = explode('@', $authUser->email, 2);
        $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3)) . '@' . $domain;

        Log::info('PasswordSetupController: forgot password link dikirim', [
            'auth_user_id' => $authUser->id,
        ]);

        return redirect()->route('password-setup.check-email', [
            'email' => $maskedEmail,
            'type'  => 'reset',
        ]);
    }

    // =========================================================================
    // HELPER: Generate token & kirim email
    // =========================================================================

    /**
     * Generate token, simpan ke auth_users, kirim email.
     * $type'setup' (akun baru) | 'reset' (forgot password)
     * Dipanggil dari AuthController (setup) dan submitForgotPassword (reset).
     */
    public static function generateAndSendToken(object $authUser, string $type = 'setup'): void
    {
        $token   = Str::random(64);
        $expires = now()->addMinutes(30);

        // Simpan hash di DB; URL memakai plaintext token
        DB::table('auth_users')->where('id', $authUser->id)->update([
            'cp_token'            => hash('sha256', $token),
            'cp_token_expires_at' => $expires,
            'updated_at'          => now(),
        ]);

        if (empty($authUser->email)) {
            Log::warning('PasswordSetupController: tidak bisa kirim email — auth_user tidak punya email', [
                'auth_user_id' => $authUser->id,
            ]);
            return;
        }

        try {
            // Token is always validated by EcoSystem (where it is stored).
            // After successful setup, customers are redirected to Jarvies automatically.
            $isCustomer = !empty($authUser->customer_id);
            $baseUrl = rtrim(config('app.url'), '/');
            // URL memakai plaintext token; DB menyimpan hash-nya
            $link    = $baseUrl . '/change-password?token=' . $token;
            $appName = $isCustomer ? 'Jarvies' : config('app.name', 'ECoSystem');

            if ($type === 'reset') {
                $subject = "Reset Your {$appName} Password";
                $body    = <<<HTML
<p>Hello,</p>
<p>We received a request to reset the password for your <strong>{$appName}</strong> account.</p>
<p>Click the button below to set a new password:</p>
<p style="margin:24px 0;">
  <a href="{$link}"
     style="background:#991b1b;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;">
    Reset My Password
  </a>
</p>
<p>This link is valid for <strong>30 minutes</strong>.</p>
<p>If you did not request a password reset, you can safely ignore this email. Your password will not change.</p>
<br>
<p>Regards,<br><strong>The {$appName} Team</strong></p>
HTML;
            } else {
                $subject = "{$appName} Account Activation — Set Your Password";
                $body    = <<<HTML
<p>Hello,</p>
<p>Your account on <strong>{$appName}</strong> has been created by our team.</p>
<p>Please click the button below to set your password before you can sign in:</p>
<p style="margin:24px 0;">
  <a href="{$link}"
     style="background:#991b1b;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;">
    Set My Password
  </a>
</p>
<p>This link is valid for <strong>30 minutes</strong>.</p>
<p>If you did not request this, please contact your administrator.</p>
<br>
<p>Regards,<br><strong>The {$appName} Team</strong></p>
HTML;
            }

            $sender     = config('services.microsoft_graph.sender_email');
            $graphToken = self::getGraphToken();

            Http::withToken($graphToken)->post(
                rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/') . "/users/{$sender}/sendMail",
                [
                    'message' => [
                        'subject' => $subject,
                        'body'    => ['contentType' => 'HTML', 'content' => $body],
                        'toRecipients' => [
                            ['emailAddress' => ['address' => $authUser->email]],
                        ],
                    ],
                    'saveToSentItems' => true,
                ]
            );

            Log::info('PasswordSetupController: email terkirim', [
                'auth_user_id' => $authUser->id,
                'email'        => $authUser->email,
                'type'         => $type,
            ]);
        } catch (\Exception $e) {
            Log::error('PasswordSetupController: gagal kirim email', [
                'auth_user_id' => $authUser->id,
                'type'         => $type,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mask email untuk ditampilkan ke user: "ab***@domain.com"
     */
    private function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) return $email;
        [$local, $domain] = explode('@', $email, 2);
        return substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3)) . '@' . $domain;
    }

    /**
     * Ambil OAuth2 access token dari Microsoft.
     */
    private static function getGraphToken(): string
    {
        // Gunakan config() agar berfungsi saat config:cache di production
        $tenantId = config('services.microsoft_graph.tenant_id');
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.microsoft_graph.client_id'),
                'client_secret' => config('services.microsoft_graph.client_secret'),
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to obtain access token' . $response->body());
        }

        return $response->json('access_token');
    }
}
