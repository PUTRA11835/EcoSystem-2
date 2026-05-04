<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Controllers\PasswordSetupController;
use App\Models\LoginActivity;
use Illuminate\Support\Facades\Http;
use Exception;

class AuthController extends Controller
{
    /** Nama cookie remember-me yang dikirim ke browser */
    public const REMEMBER_COOKIE = 'remember_ecosystem';

    /** Durasi remember-me dalam hari */
    private const REMEMBER_DAYS = 30;

    // =========================================================================
    // HELPER: Bangun session data dari employee_id
    // =========================================================================

    /**
     * Ambil data employee dari DB dan kembalikan array untuk disimpan ke session.
     * Dipakai saat login maupun saat auto-restore session via remember-me cookie.
     *
     * @return array|null  null jika employee tidak ditemukan atau tidak aktif
     */
    public static function buildEmployeeSessionData(int $employeeId): ?array
    {
        $employee = DB::table('employee as e')
            ->join('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
            ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
            ->leftJoin('employee_role as r', 'e.role_id', '=', 'r.id')
            ->where('e.employee_id', $employeeId)
            ->select(
                'e.employee_id',
                'e.eci',
                'e.is_active',
                DB::raw("CONCAT(eb.first_name, ' ', COALESCE(eb.last_name, '')) as full_name"),
                'eb.nick_name',
                'ea.email_personal as email',
                'ea.cell_phone as phone_number',
                'eb.position',
                'eb.employee_subgroup as department',
                'r.id as role_id',
                'r.name as role_name'
            )
            ->first();

        if (!$employee || !$employee->is_active) {
            return null;
        }

        // Ambil email dari auth_users (bukan employee_address) agar konsisten
        $authEmail = DB::table('auth_users')
            ->where('employee_id', $employeeId)
            ->value('email');

        $tokenData = $employee->eci . '|' . time() . '|employee';
        $token     = base64_encode($tokenData);

        return [
            'token'    => $token,
            'userData' => [
                'id'         => (int) $employee->employee_id,
                'type'       => 'employee',
                'eci'        => $employee->eci,
                'name'       => $employee->full_name,
                'nick_name'  => $employee->nick_name ?: explode(' ', trim($employee->full_name))[0],
                'email'      => $authEmail ?? $employee->email,
                'phone'      => $employee->phone_number,
                'position'   => $employee->position,
                'department' => $employee->department,
                'role'       => [
                    'id'   => (int) $employee->role_id,
                    'name' => $employee->role_name,
                ],
            ],
        ];
    }

    /**
     * Show login page (untuk web)
     */
    public function showLogin()
    {
        try {
            $sessionId = session()->getId();
            $hasToken = session()->has('auth_token');
            
            Log::channel('daily')->info('=== SHOW LOGIN PAGE ===', [
                'timestamp' => now()->toDateTimeString(),
                'session_id' => $sessionId,
                'has_session' => $hasToken,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'referer' => request()->header('referer')
            ]);

            if ($hasToken) {
                Log::channel('daily')->info('User already authenticated, redirecting to dashboard', [
                    'session_id' => $sessionId,
                ]);
                return redirect()->route('dashboard');
            }

            // Request Client Hints from the browser for better device detection on login
            return response(view('auth.login'))
                ->header('Accept-CH', 'Sec-CH-UA-Model, Sec-CH-UA-Platform, Sec-CH-UA-Mobile')
                ->header('Permissions-Policy', 'ch-ua-model=(*), ch-ua-platform=(*)');

            
        } catch (Exception $e) {
            Log::channel('daily')->error('Error in showLogin method', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('login')->with('error', 'An unexpected error occurred while loading the login page. Please try again.');
        }
    }

    /**
     * Login API (untuk AJAX/fetch)
     */
    // =========================================================================
    // HELPER: Parse User-Agent string into device/browser/os info
    // =========================================================================

    /**
     * Parse User-Agent string + optional Client Hints into brand/model/type/browser/os.
     *
     * @param string $ua        Raw User-Agent header
     * @param string $chModel   Sec-CH-UA-Model header (Chrome/Edge sends device model)
     * @param string $chPlatform Sec-CH-UA-Platform header (e.g. "Windows", "Android")
     */
    private static function parseUserAgent(string $ua, string $chModel = '', string $chPlatform = ''): array
    {
        // ── Device type ────────────────────────────────────────────────────────
        $deviceType = 'desktop';
        if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Windows Phone/i', $ua)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/iPad|Tablet|PlayBook|Kindle|Fire\s?HD|KFTT|KFJWI|KFOT|Android(?!.*Mobile)/i', $ua)) {
            $deviceType = 'tablet';
        }

        // ── Brand & model ──────────────────────────────────────────────────────
        $deviceBrand = null;
        $deviceModel = null;

        // ── Apple ──────────────────────────────────────────────────────────────
        if (preg_match('/iPhone/i', $ua)) {
            $deviceBrand = 'Apple';
            $deviceModel = 'iPhone';
        } elseif (preg_match('/iPad/i', $ua)) {
            $deviceBrand = 'Apple';
            $deviceModel = 'iPad';
        } elseif (preg_match('/iPod/i', $ua)) {
            $deviceBrand = 'Apple';
            $deviceModel = 'iPod Touch';
        } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
            $deviceBrand = 'Apple';
            // Try to determine MacBook vs iMac vs Mac Pro from CPU arch hints
            if (preg_match('/Mac OS X 10_[\d_]+/i', $ua) && !preg_match('/Mobile/i', $ua)) {
                $deviceModel = 'Mac';
            } else {
                $deviceModel = 'Mac';
            }

        // ── Samsung ────────────────────────────────────────────────────────────
        } elseif (preg_match('/SM-([A-Z][0-9]{3,4}[A-Z0-9]*)/i', $ua, $m)) {
            $deviceBrand = 'Samsung';
            // Identify series: A=mid-range, S=flagship, M=budget, F=FE, Tab=tablet
            $code = strtoupper($m[1]);
            $series = substr($code, 0, 1);
            $seriesMap = ['S' => 'Galaxy S', 'A' => 'Galaxy A', 'M' => 'Galaxy M', 'F' => 'Galaxy F', 'Z' => 'Galaxy Z', 'T' => 'Galaxy Tab', 'X' => 'Galaxy X'];
            $label = $seriesMap[$series] ?? 'Galaxy';
            $deviceModel = $label . ' (' . $code . ')';
        } elseif (preg_match('/Samsung[_\s]?([\w\s]+?)(?:\sBuild|\)|;)/i', $ua, $m)) {
            $deviceBrand = 'Samsung';
            $deviceModel = 'Samsung ' . trim($m[1]);
        } elseif (preg_match('/SamsungBrowser/i', $ua)) {
            $deviceBrand = 'Samsung';

        // ── Xiaomi / Redmi / POCO ──────────────────────────────────────────────
        } elseif (preg_match('/Redmi\s+([\w\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Xiaomi';
            $deviceModel = 'Redmi ' . trim($m[1]);
        } elseif (preg_match('/POCO\s+([\w\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Xiaomi';
            $deviceModel = 'POCO ' . trim($m[1]);
        } elseif (preg_match('/(?:^|\s|;)M\d{4}[A-Z0-9]+(?:\s|Build|\))/i', $ua, $m)) {
            // Xiaomi model code like M2007J20CG
            $deviceBrand = 'Xiaomi';
        } elseif (preg_match('/Mi\s+([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Xiaomi';
            $deviceModel = 'Mi ' . trim($m[1]);
        } elseif (preg_match('/Xiaomi|MIUI/i', $ua)) {
            $deviceBrand = 'Xiaomi';

        // ── OPPO / OnePlus / realme (all BBK Electronics) ─────────────────────
        } elseif (preg_match('/CPH\d{4}|PFVM\d+|PERM\d+/i', $ua, $m)) {
            $deviceBrand = 'OPPO';
            $deviceModel = 'OPPO ' . $m[0];
        } elseif (preg_match('/OPPO\s*([\w\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'OPPO';
            $deviceModel = 'OPPO ' . trim($m[1]);
        } elseif (preg_match('/OPPO/i', $ua)) {
            $deviceBrand = 'OPPO';
        } elseif (preg_match('/LE\d{4}[A-Z]*|OnePlus\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'OnePlus';
            $deviceModel = 'OnePlus ' . trim($m[1] ?? '');
        } elseif (preg_match('/ONEPLUS|OnePlus/i', $ua)) {
            $deviceBrand = 'OnePlus';
        } elseif (preg_match('/realme\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'realme';
            $deviceModel = 'realme ' . trim($m[1]);
        } elseif (preg_match('/realme/i', $ua)) {
            $deviceBrand = 'realme';

        // ── vivo ───────────────────────────────────────────────────────────────
        } elseif (preg_match('/vivo\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'vivo';
            $deviceModel = 'vivo ' . trim($m[1]);
        } elseif (preg_match('/vivo/i', $ua)) {
            $deviceBrand = 'vivo';

        // ── Huawei / Honor ─────────────────────────────────────────────────────
        } elseif (preg_match('/(?:HMA|EML|CLT|VOG|ANA|NOP|BLA|PCT|KSA|JSN|STF|MAR|VCE|HRY|JKM)-[A-Z0-9]+/i', $ua, $m)) {
            $deviceBrand = 'Huawei';
            $deviceModel = 'Huawei ' . strtoupper($m[0]);
        } elseif (preg_match('/Huawei[_\s-]*([\w\s-]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Huawei';
            $deviceModel = 'Huawei ' . trim($m[1]);
        } elseif (preg_match('/HUAWEI/i', $ua)) {
            $deviceBrand = 'Huawei';
        } elseif (preg_match('/Honor\s*([\w\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Honor';
            $deviceModel = 'Honor ' . trim($m[1]);
        } elseif (preg_match('/HONOR/i', $ua)) {
            $deviceBrand = 'Honor';

        // ── Google Pixel / Nexus ───────────────────────────────────────────────
        } elseif (preg_match('/Pixel\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Google';
            $deviceModel = 'Pixel ' . trim($m[1]);
        } elseif (preg_match('/Nexus\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Google';
            $deviceModel = 'Nexus ' . trim($m[1]);

        // ── Sony ───────────────────────────────────────────────────────────────
        } elseif (preg_match('/Xperia\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Sony';
            $deviceModel = 'Xperia ' . trim($m[1]);
        } elseif (preg_match('/Sony(?:Ericsson)?\s*([\w\d]+)/i', $ua, $m)) {
            $deviceBrand = 'Sony';
            $deviceModel = trim($m[1]) ?: null;

        // ── Motorola ───────────────────────────────────────────────────────────
        } elseif (preg_match('/Moto\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Motorola';
            $deviceModel = 'Moto ' . trim($m[1]);
        } elseif (preg_match('/XT\d{4}(?:-\d)?/i', $ua, $m)) {
            // Motorola model codes like XT2125-4
            $deviceBrand = 'Motorola';
            $deviceModel = 'Moto ' . $m[0];
        } elseif (preg_match('/Motorola/i', $ua)) {
            $deviceBrand = 'Motorola';

        // ── Nokia ─────────────────────────────────────────────────────────────
        } elseif (preg_match('/Nokia\s*([\w\d\.]+)/i', $ua, $m)) {
            $deviceBrand = 'Nokia';
            $deviceModel = 'Nokia ' . $m[1];

        // ── Lenovo (tablets / phones) ──────────────────────────────────────────
        } elseif (preg_match('/TB-[\w\d]+|Lenovo\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Lenovo';
            $deviceModel = isset($m[1]) && trim($m[1]) ? 'Lenovo ' . trim($m[1]) : null;

        // ── ASUS (ZenFone, ROG Phone, ZenPad) ─────────────────────────────────
        } elseif (preg_match('/ASUS[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'ASUS';
            $deviceModel = 'ASUS ' . trim($m[1]);

        // ── HTC ────────────────────────────────────────────────────────────────
        } elseif (preg_match('/HTC[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'HTC';
            $deviceModel = 'HTC ' . trim($m[1]);

        // ── LG ─────────────────────────────────────────────────────────────────
        } elseif (preg_match('/LM-([A-Z0-9]+)/i', $ua, $m)) {
            $deviceBrand = 'LG';
            $deviceModel = 'LG ' . $m[1];
        } elseif (preg_match('/LG-([A-Z0-9]+)/i', $ua, $m)) {
            $deviceBrand = 'LG';
            $deviceModel = 'LG ' . $m[1];

        // ── TCL / Alcatel ──────────────────────────────────────────────────────
        } elseif (preg_match('/TCL[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'TCL';
            $deviceModel = 'TCL ' . trim($m[1]);
        } elseif (preg_match('/Alcatel[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Alcatel';
            $deviceModel = 'Alcatel ' . trim($m[1]);

        // ── ZTE ────────────────────────────────────────────────────────────────
        } elseif (preg_match('/ZTE[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'ZTE';
            $deviceModel = 'ZTE ' . trim($m[1]);

        // ── Infinix ────────────────────────────────────────────────────────────
        } elseif (preg_match('/Infinix[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Infinix';
            $deviceModel = 'Infinix ' . trim($m[1]);

        // ── Tecno ──────────────────────────────────────────────────────────────
        } elseif (preg_match('/TECNO[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Tecno';
            $deviceModel = 'Tecno ' . trim($m[1]);

        // ── itel ───────────────────────────────────────────────────────────────
        } elseif (preg_match('/itel[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'itel';
            $deviceModel = 'itel ' . trim($m[1]);

        // ── Wiko ───────────────────────────────────────────────────────────────
        } elseif (preg_match('/WIKO[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Wiko';
            $deviceModel = 'Wiko ' . trim($m[1]);

        // ── Sharp ──────────────────────────────────────────────────────────────
        } elseif (preg_match('/Sharp[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Sharp';
            $deviceModel = 'Sharp ' . trim($m[1]);

        // ── Fujitsu ────────────────────────────────────────────────────────────
        } elseif (preg_match('/Fujitsu/i', $ua)) {
            $deviceBrand = 'Fujitsu';

        // ── Meizu ──────────────────────────────────────────────────────────────
        } elseif (preg_match('/meizu[_\s]?([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Meizu';
            $deviceModel = 'Meizu ' . trim($m[1]);

        // ── Nothing Phone ─────────────────────────────────────────────────────
        } elseif (preg_match('/Nothing\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Nothing';
            $deviceModel = 'Nothing ' . trim($m[1]);

        // ── Fairphone ─────────────────────────────────────────────────────────
        } elseif (preg_match('/Fairphone\s*([\w\d\s]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'Fairphone';
            $deviceModel = 'Fairphone ' . trim($m[1]);

        // ── CAT (Caterpillar rugged) ───────────────────────────────────────────
        } elseif (preg_match('/CAT\s*(S[\w\d]+?)(?:\sBuild|\))/i', $ua, $m)) {
            $deviceBrand = 'CAT';
            $deviceModel = 'CAT ' . $m[1];

        // ── BlackBerry ────────────────────────────────────────────────────────
        } elseif (preg_match('/BlackBerry[_\s]?([\w\d]+)?/i', $ua, $m)) {
            $deviceBrand = 'BlackBerry';
            $deviceModel = isset($m[1]) && trim($m[1]) ? 'BlackBerry ' . $m[1] : null;

        // ── Amazon Kindle / Fire ───────────────────────────────────────────────
        } elseif (preg_match('/Kindle|KFTT|KFJWI|KFOT|KFSOWI|KFTHWI|Fire\s?HD/i', $ua, $m)) {
            $deviceBrand = 'Amazon';
            $deviceModel = preg_match('/Fire\s?HD/i', $ua) ? 'Fire HD' : 'Kindle Fire';

        // ── Microsoft Surface (detectable via Edge + Touch UA token) ──────────
        } elseif (preg_match('/Touch|ARM/i', $ua) && preg_match('/Windows NT/i', $ua) && preg_match('/Edg/i', $ua)) {
            $deviceBrand = 'Microsoft';
            $deviceModel = 'Surface';

        // ── Google Chromebook ─────────────────────────────────────────────────
        } elseif (preg_match('/CrOS/i', $ua)) {
            $deviceBrand = 'Google';
            $deviceModel = 'Chromebook';
            $deviceType  = 'desktop';

        // ── Steam Deck ────────────────────────────────────────────────────────
        } elseif (preg_match('/SteamDeck|Valve Steam/i', $ua)) {
            $deviceBrand = 'Valve';
            $deviceModel = 'Steam Deck';
            $deviceType  = 'desktop';

        // ── Windows PC (generic — laptop/desktop brand NOT in standard UA) ─────
        } elseif (preg_match('/Windows NT/i', $ua)) {
            $deviceBrand = 'Windows PC';
            $deviceModel = null; // brand (Dell/HP/etc.) not available in UA

        // ── Linux desktop ─────────────────────────────────────────────────────
        } elseif (preg_match('/Linux/i', $ua) && $deviceType === 'desktop') {
            // Detect common distros from UA if present
            if (preg_match('/Ubuntu/i', $ua)) {
                $deviceBrand = 'Ubuntu PC';
            } elseif (preg_match('/Fedora/i', $ua)) {
                $deviceBrand = 'Fedora PC';
            } elseif (preg_match('/Debian/i', $ua)) {
                $deviceBrand = 'Debian PC';
            } else {
                $deviceBrand = 'Linux PC';
            }
        }

        // ── Browser ────────────────────────────────────────────────────────────
        $browser = 'Unknown';
        if (preg_match('/Edg\//i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/OPR\//i', $ua) || preg_match('/Opera/i', $ua)) {
            $browser = 'Opera';
        } elseif (preg_match('/SamsungBrowser/i', $ua)) {
            $browser = 'Samsung Browser';
        } elseif (preg_match('/UCBrowser/i', $ua)) {
            $browser = 'UC Browser';
        } elseif (preg_match('/YaBrowser/i', $ua)) {
            $browser = 'Yandex Browser';
        } elseif (preg_match('/Chrome\/[\d.]+/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/[\d.]+/i', $ua) && preg_match('/Version\//i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/MSIE|Trident/i', $ua)) {
            $browser = 'Internet Explorer';
        }

        // ── OS ─────────────────────────────────────────────────────────────────
        $os = 'Unknown';
        if (preg_match('/Windows NT ([\d.]+)/i', $ua, $m)) {
            $versions = ['10.0' => 'Windows 10/11', '6.3' => 'Windows 8.1', '6.2' => 'Windows 8', '6.1' => 'Windows 7'];
            $os = $versions[$m[1]] ?? 'Windows';
        } elseif (preg_match('/Mac OS X ([\d_]+)/i', $ua, $m)) {
            $os = 'macOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Android ([\d.]+)/i', $ua, $m)) {
            $os = 'Android ' . $m[1];
        } elseif (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m)) {
            $os = 'iOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/iPad.*OS ([\d_]+)/i', $ua, $m)) {
            $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        }

        // ── Client Hints override ──────────────────────────────────────────────
        // Chrome/Edge sends Sec-CH-UA-Model with the exact device model name (mostly mobile).
        // If we have it, use it as device_model. Sec-CH-UA-Platform can refine device type.
        $chModel    = trim($chModel);
        $chPlatform = strtolower(trim($chPlatform));

        if ($chModel !== '' && $chModel !== 'Unknown') {
            $deviceModel = $chModel;
            // If brand wasn't detected from UA, try to infer from model hint
            if ($deviceBrand === null || $deviceBrand === 'Windows PC' || $deviceBrand === 'Linux PC') {
                // Common model prefixes
                if (preg_match('/^SM-/i', $chModel))         { $deviceBrand = 'Samsung'; }
                elseif (preg_match('/^Pixel/i', $chModel))   { $deviceBrand = 'Google'; }
                elseif (preg_match('/^Redmi|^POCO|^Mi /i', $chModel)) { $deviceBrand = 'Xiaomi'; }
                elseif (preg_match('/^iPhone|^iPad/i', $chModel))     { $deviceBrand = 'Apple'; }
                elseif (preg_match('/^CPH|^OPPO/i', $chModel))        { $deviceBrand = 'OPPO'; }
                elseif (preg_match('/^realme/i', $chModel))  { $deviceBrand = 'realme'; }
                elseif (preg_match('/^vivo/i', $chModel))    { $deviceBrand = 'vivo'; }
                elseif (preg_match('/^OnePlus/i', $chModel)) { $deviceBrand = 'OnePlus'; }
                elseif (preg_match('/^Moto|^XT\d/i', $chModel)) { $deviceBrand = 'Motorola'; }
                elseif (preg_match('/^Nokia/i', $chModel))   { $deviceBrand = 'Nokia'; }
                elseif (preg_match('/^Xperia/i', $chModel))  { $deviceBrand = 'Sony'; }
                elseif (preg_match('/^Galaxy/i', $chModel))  { $deviceBrand = 'Samsung'; }
                elseif (preg_match('/^Surface/i', $chModel)) { $deviceBrand = 'Microsoft'; }
                elseif (preg_match('/^Chromebook/i', $chModel)) { $deviceBrand = 'Google'; }
            }
        }

        if ($chPlatform === 'android')  { $deviceType = $deviceType === 'tablet' ? 'tablet' : 'mobile'; }
        elseif ($chPlatform === 'ios')  { $deviceType = 'mobile'; }
        elseif (in_array($chPlatform, ['windows', 'macos', 'linux', 'chromeos'])) { $deviceType = 'desktop'; }

        return compact('deviceType', 'deviceBrand', 'deviceModel', 'browser', 'os');
    }

    /**
     * Lookup city/country from IP via ip-api.com (free, no key required).
     * Times out in 1 second to avoid slowing down login.
     */
    private static function resolveIpLocation(string $ip): array
    {
        // Skip private/local IPs
        if (in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return ['city' => 'Local', 'country' => 'Local'];
        }

        try {
            $res = Http::timeout(1)->get("http://ip-api.com/json/{$ip}?fields=status,city,country");
            if ($res->successful()) {
                $data = $res->json();
                if (($data['status'] ?? '') === 'success') {
                    return ['city' => $data['city'] ?? null, 'country' => $data['country'] ?? null];
                }
            }
        } catch (\Throwable) {
            // geolocation failure is non-fatal
        }

        return ['city' => null, 'country' => null];
    }

    /**
     * Record a login/logout activity entry.
     */
    public static function recordActivity(
        int    $userId,
        int    $roleId,
        string $userName,
        string $userType,
        string $ip,
        string $userAgent,
        string $status,    // 'success' | 'failed' | 'logout'
        string $chModel    = '',
        string $chPlatform = ''
    ): void {
        try {
            $uaParsed = self::parseUserAgent($userAgent, $chModel, $chPlatform);
            $location = self::resolveIpLocation($ip);

            LoginActivity::create([
                'user_id'          => $userId,
                'role_id'          => $roleId,
                'user_name'        => $userName,
                'user_type'        => $userType,
                'ip_address'       => $ip,
                'user_agent'       => $userAgent,
                'device_type'      => $uaParsed['deviceType'],
                'device_brand'     => $uaParsed['deviceBrand'],
                'device_model'     => $uaParsed['deviceModel'],
                'browser'          => $uaParsed['browser'],
                'os'               => $uaParsed['os'],
                'location_city'    => $location['city'],
                'location_country' => $location['country'],
                'login_at'         => $status !== 'logout' ? now() : null,
                'logout_at'        => $status === 'logout' ? now() : null,
                'status'           => $status,
                'created_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('recordActivity: failed to write login_activity', ['error' => $e->getMessage()]);
        }
    }

    public function login(Request $request)
    {
        // Generate unique request ID untuk tracking
        $requestId = uniqid('login_', true);
        
        Log::channel('daily')->info('=== LOGIN REQUEST START ===', [
            'request_id' => $requestId,
            'timestamp' => now()->toDateTimeString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'session_id' => $request->session()->getId()
        ]);

        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'email' => 'required|string',
                'password' => 'required|string|min:6',
                'remember' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                Log::channel('daily')->warning('Validation failed', [
                    'request_id' => $requestId,
                    'errors' => $validator->errors()->toArray(),
                    'input_email' => $request->input('email')
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = trim($request->email);
            $password = $request->password;
            $remember = $request->boolean('remember');

            Log::channel('daily')->info('Validation passed', [
                'request_id' => $requestId,
                'email' => $email,
                'password_length' => strlen($password),
                'remember' => $remember
            ]);

            // CEK AUTH_USERS TABLE (centralized auth)
            Log::channel('daily')->info('=== CHECKING AUTH_USERS TABLE ===', [
                'request_id' => $requestId,
                'search_email' => $email
            ]);

            // Cek auth_users: email, username (ECI / customer_code), atau phone
            $authUser = DB::table('auth_users')
                ->where(function($query) use ($email) {
                    $query->where('email', $email)
                          ->orWhere('username', $email)
                          ->orWhere('phone', $email);
                })
                ->where('is_active', true)
                ->first();

            Log::channel('daily')->info('Auth user query executed', [
                'request_id' => $requestId,
                'auth_user_found' => $authUser ? 'YES' : 'NO',
                'auth_user_id' => $authUser->id ?? null,
            ]);

            if (!$authUser) {
                Log::channel('daily')->warning('=== USER NOT FOUND IN AUTH_USERS ===', [
                    'request_id' => $requestId,
                    'email_searched' => $email,
                    'ip_address' => $request->ip(),
                    'timestamp' => now()->toDateTimeString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            // Verifikasi password dari auth_users
            $passwordValid = Hash::check($password, $authUser->password);

            Log::channel('daily')->info('Password verification result', [
                'request_id' => $requestId,
                'auth_user_id' => $authUser->id,
                'password_valid' => $passwordValid ? 'YES' : 'NO'
            ]);

            if (!$passwordValid) {
                Log::channel('daily')->warning('Invalid password attempt', [
                    'request_id' => $requestId,
                    'auth_user_id' => $authUser->id,
                    'email' => $email,
                    'ip_address' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            // Tentukan tipe user dari FK
            $isEmployee = !is_null($authUser->employee_id);
            $isCustomer = !is_null($authUser->customer_id);

            // Customer tidak memiliki akses ke EcoSystem — gunakan portal Jarvies
            if ($isCustomer) {
                Log::channel('daily')->warning('=== CUSTOMER LOGIN ATTEMPT BLOCKED ===', [
                    'request_id'    => $requestId,
                    'auth_user_id'  => $authUser->id,
                    'ip_address'    => $request->ip(),
                    'timestamp'     => now()->toDateTimeString(),
                ]);

                return response()->json([
                    'success'    => false,
                    'message'    => 'Access denied. Please use the Jarvies customer portal.',
                ], 403);
            }

            // Cek is_already_cp — user baru wajib verifikasi email & ganti password dulu
            if (!$authUser->is_already_cp) {
                if (empty($authUser->email)) {
                    Log::channel('daily')->warning('=== USER REQUIRES PASSWORD SETUP BUT HAS NO EMAIL ===', [
                        'request_id'   => $requestId,
                        'auth_user_id' => $authUser->id,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Your account does not have a registered email. Please contact your administrator.',
                        'request_id' => $requestId,
                    ], 403);
                }

                PasswordSetupController::generateAndSendToken($authUser);

                [$local, $domain] = explode('@', $authUser->email, 2);
                $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3)) . '@' . $domain;

                Log::channel('daily')->info('=== USER REQUIRES PASSWORD SETUP ===', [
                    'request_id'   => $requestId,
                    'auth_user_id' => $authUser->id,
                    'is_employee'  => $isEmployee,
                ]);

                return response()->json([
                    'success'                 => true,
                    'require_password_change' => true,
                    'message'                 => 'Please check your email to set up your new password.',
                    'email'                   => $maskedEmail,
                ]);
            }

            if ($isEmployee) {
                // Bangun session data via helper (reusable oleh remember-me restore)
                $sessionData = self::buildEmployeeSessionData($authUser->employee_id);

                if (!$sessionData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is inactive',
                    ], 403);
                }

                // ── Cek akses EcoSystem via role 'User System Registered' ──────
                $hasSystemAccess = DB::table('employee_role_assignment as era')
                    ->join('employee_role as er', 'era.role_id', '=', 'er.id')
                    ->where('era.employee_id', $authUser->employee_id)
                    ->where('er.name', 'User System Registered')
                    ->exists();

                if (!$hasSystemAccess) {
                    // Record failed attempt before returning
                    self::recordActivity(
                        userId:     $authUser->employee_id,
                        roleId:     0,
                        userName:   $sessionData['userData']['name'] ?? $email,
                        userType:   'employee',
                        ip:         $request->ip(),
                        userAgent:  $request->userAgent() ?? '',
                        status:     'failed',
                        chModel:    $request->header('Sec-CH-UA-Model', ''),
                        chPlatform: $request->header('Sec-CH-UA-Platform', '')
                    );

                    return response()->json([
                        'success' => false,
                        'message' => 'Your account does not have access to EcoSystem. Please contact your administrator.',
                    ], 403);
                }

                $token    = $sessionData['token'];
                $userData = $sessionData['userData'];

                // Update last_login_at
                $rememberUpdates = ['last_login_at' => now()];

                // ── Remember Me ──────────────────────────────────────────────
                $responseCookies = [];
                if ($remember) {
                    $rawRememberToken = Str::random(60);
                    $rememberUpdates['remember_token'] = hash('sha256', $rawRememberToken);

                    // Cookie HttpOnly, SameSite=Lax, 30 hari
                    // secure: ikut SESSION_SECURE_COOKIE (.env) — false lokal, true production HTTPS
                    $responseCookies[] = cookie(
                        self::REMEMBER_COOKIE,
                        $rawRememberToken,
                        self::REMEMBER_DAYS * 24 * 60, // menit
                        '/',
                        null,
                        config('session.secure'),
                        true   // httpOnly
                    );
                } else {
                    // Hapus remember token jika tidak centang
                    $rememberUpdates['remember_token'] = null;
                }

                DB::table('auth_users')->where('id', $authUser->id)->update($rememberUpdates);

                $request->session()->put('auth_token', $token);
                $request->session()->put('user', $userData);
                $request->session()->regenerate();
                $request->session()->save();

                Log::channel('daily')->info('=== EMPLOYEE LOGIN SUCCESSFUL ===', [
                    'request_id'  => $requestId,
                    'employee_id' => $userData['id'],
                    'eci'         => $userData['eci'],
                    'remember_me' => $remember,
                    'ip_address'  => $request->ip(),
                    'timestamp'   => now()->toDateTimeString()
                ]);

                // Record login activity (include Client Hints for better device detection)
                self::recordActivity(
                    userId:     $userData['id'],
                    roleId:     $userData['role']['id'] ?? 0,
                    userName:   $userData['name'] ?? $userData['eci'] ?? 'Unknown',
                    userType:   'employee',
                    ip:         $request->ip(),
                    userAgent:  $request->userAgent() ?? '',
                    status:     'success',
                    chModel:    $request->header('Sec-CH-UA-Model', ''),
                    chPlatform: $request->header('Sec-CH-UA-Platform', '')
                );

                $response = response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data'    => [
                        'token' => $token,
                        'user'  => $userData
                    ],
                ], 200);

                foreach ($responseCookies as $cookie) {
                    $response->withCookie($cookie);
                }

                return $response;
            }

            // auth_user tanpa employee_id maupun customer_id
            Log::channel('daily')->warning('=== AUTH USER HAS NO LINKED ACCOUNT ===', [
                'request_id' => $requestId,
                'auth_user_id' => $authUser->id,
                'email_searched' => $email,
                'ip_address' => $request->ip(),
                'timestamp' => now()->toDateTimeString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 404);

        } catch (Exception $e) {
            Log::channel('daily')->error('=== CRITICAL LOGIN ERROR ===', [
                'request_id' => $requestId,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace_summary' => substr($e->getTraceAsString(), 0, 500),
                'email' => $email ?? 'N/A',
                'ip_address' => $request->ip(),
                'timestamp' => now()->toDateTimeString()
            ]);

            // Log tambahan untuk debugging
            Log::channel('daily')->error('Exception details', [
                'request_id' => $requestId,
                'previous_exception' => $e->getPrevious() ? $e->getPrevious()->getMessage() : 'None',
                'exception_trace' => $e->getTrace()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'A system error occurred. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $requestId = uniqid('logout_', true);
        
        Log::channel('daily')->info('=== LOGOUT REQUEST ===', [
            'request_id' => $requestId,
            'timestamp' => now()->toDateTimeString(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'session_id' => $request->session()->getId(),
            'has_token' => $request->session()->has('auth_token'),
        ]);

        try {
            if ($request->isMethod('get')) {
                return redirect()->route('login')->with('info', 'Please use logout button');
            }

            // Proses logout
            $userData = $request->session()->get('user');

            // Record logout activity before session is flushed
            if (!empty($userData['id'])) {
                self::recordActivity(
                    userId:     $userData['id'],
                    roleId:     $userData['role']['id'] ?? 0,
                    userName:   $userData['name'] ?? 'Unknown',
                    userType:   'employee',
                    ip:         $request->ip(),
                    userAgent:  $request->userAgent() ?? '',
                    status:     'logout',
                    chModel:    $request->header('Sec-CH-UA-Model', ''),
                    chPlatform: $request->header('Sec-CH-UA-Platform', '')
                );
            }

            // Hapus remember_token dari DB agar cookie lama tidak bisa restore session
            if (!empty($userData['id'])) {
                DB::table('auth_users')
                    ->where('employee_id', $userData['id'])
                    ->update(['remember_token' => null]);
            }

            $request->session()->flush();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::channel('daily')->info('=== LOGOUT SUCCESSFUL ===', [
                'request_id' => $requestId,
                'employee_id' => $userData['id'] ?? null,
                'ip_address'  => $request->ip(),
                'timestamp'   => now()->toDateTimeString()
            ]);

            // Hapus remember-me cookie dari browser
            $expiredCookie = cookie(self::REMEMBER_COOKIE, '', -1);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logout successful',
                ], 200)->withCookie($expiredCookie);
            }

            return redirect()->route('login')
                ->with('success', 'You have been logged out')
                ->withCookie($expiredCookie);

        } catch (Exception $e) {
            Log::channel('daily')->error('Error during logout', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout',
            ], 500);
        }
    }

    /**
     * Kembalikan data user yang sedang login.
     * Menggunakan session sebagai sumber data — lebih aman dari bearer token parsing.
     */
    public function me(Request $request)
    {
        try {
            $user = $request->session()->get('user');

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data'    => $user,
            ], 200);

        } catch (Exception $e) {
            Log::channel('daily')->error('Error in me()', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your profile. Please refresh and try again.',
            ], 500);
        }
    }
}

