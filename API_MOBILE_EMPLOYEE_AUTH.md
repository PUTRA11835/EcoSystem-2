# API Autentikasi Mobile — Khusus Employee

> **Laravel Sanctum · Access Token 15 menit · Refresh Token 7 hari**

---

## Daftar Isi

1. [Gambaran Umum](#gambaran-umum)
2. [Ketentuan Role](#ketentuan-role)
3. [Mekanisme Token](#mekanisme-token)
4. [Endpoint API](#endpoint-api)
   - [Login](#1-login)
   - [Refresh Token](#2-refresh-token)
   - [Logout](#3-logout)
   - [Data User (Me)](#4-data-user-me)
5. [Kode Error](#kode-error)
6. [Panduan Testing Postman](#panduan-testing-postman)
7. [Integrasi Flutter](#integrasi-flutter)

---

## Gambaran Umum

API ini menyediakan sistem autentikasi **khusus employee** untuk aplikasi mobile Flutter. Sistem menggunakan **Laravel Sanctum** dengan dua jenis token:

| Token         | Kegunaan                          | Masa Berlaku |
|---------------|-----------------------------------|-------------|
| Access Token  | Mengakses semua endpoint          | 15 menit    |
| Refresh Token | Mendapatkan access token baru     | 7 hari      |

**Base URL:** `https://yourdomain.com/api/mobile/employee`

---

## Ketentuan Role

- Hanya user dengan `employee_id` yang boleh login melalui API ini.
- User dengan `customer_id` akan **ditolak** dengan HTTP 403 dan kode `NOT_EMPLOYEE`.
- Login dapat menggunakan **email**, **username (ECI)**, atau **nomor telepon**.
- Data role (jabatan) diambil dari tabel `employee_role` yang sudah ada.

---

## Mekanisme Token

```
[LOGIN] ──► access_token (15 mnt) + refresh_token (7 hari)
               │
               ▼
[PAKAI API] ─── kirim access_token di header Authorization
               │
               ├── access_token masih valid ──► lanjut
               │
               └── access_token expired  (kode: ACCESS_TOKEN_EXPIRED)
                        │
                        ▼
               [REFRESH] ─── kirim refresh_token ke POST /auth/refresh
                        │
                        ├── refresh_token masih valid ──► access_token baru + refresh_token baru
                        │
                        └── refresh_token expired  (kode: REFRESH_TOKEN_EXPIRED)
                                 │
                                 ▼
                        [LOGIN ULANG]
```

> **Token Rotation:** setiap kali refresh, semua token lama langsung dihapus dan diganti yang baru.

---

## Endpoint API

### 1. Login

Login dan dapatkan access token serta refresh token.

| Properti | Detail                             |
|----------|------------------------------------|
| Method   | `POST`                             |
| URL      | `/api/mobile/employee/auth/login`  |
| Auth     | Tidak perlu                        |

**Request Body (JSON):**

```json
{
  "email": "employee@perusahaan.com",
  "password": "password123"
}
```

> Field `email` bisa diisi dengan email, username (ECI), atau nomor telepon.

**Response Sukses (200):**

```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "access_token": "1|abcdefghijklmnopqrstuvwxyz1234567890",
    "refresh_token": "2|ABCDEFGHIJKLMNOPQRSTUVWXYZ0987654321",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": {
      "id": 10,
      "eci": "EC-0010",
      "name": "Budi Santoso",
      "email": "budi@perusahaan.com",
      "phone": "08123456789",
      "position": "Software Engineer",
      "department": "IT",
      "role": {
        "id": 2,
        "name": "Staff"
      }
    }
  }
}
```

> `expires_in` = 900 detik (15 menit).

**Response Error — Email/Password Salah (401):**

```json
{
  "success": false,
  "message": "Email atau password salah."
}
```

**Response Error — Bukan Employee (403):**

```json
{
  "success": false,
  "message": "Akses ditolak. Hanya employee yang dapat login melalui aplikasi ini.",
  "code": "NOT_EMPLOYEE"
}
```

**Response Error — Akun Tidak Aktif (403):**

```json
{
  "success": false,
  "message": "Akun Anda tidak aktif. Hubungi administrator.",
  "code": "ACCOUNT_INACTIVE"
}
```

---

### 2. Refresh Token

Gunakan refresh token untuk mendapatkan access token baru (beserta refresh token baru).

| Properti | Detail                               |
|----------|--------------------------------------|
| Method   | `POST`                               |
| URL      | `/api/mobile/employee/auth/refresh`  |
| Auth     | Tidak perlu                          |

**Request Body (JSON):**

```json
{
  "refresh_token": "2|ABCDEFGHIJKLMNOPQRSTUVWXYZ0987654321"
}
```

**Response Sukses (200):**

```json
{
  "success": true,
  "message": "Token berhasil diperbarui.",
  "data": {
    "access_token": "3|newAccessToken...",
    "refresh_token": "4|newRefreshToken...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

**Response Error — Refresh Token Expired (401):**

```json
{
  "success": false,
  "message": "Refresh token sudah expired. Silakan login ulang.",
  "code": "REFRESH_TOKEN_EXPIRED"
}
```

**Response Error — Token Tidak Valid (401):**

```json
{
  "success": false,
  "message": "Refresh token tidak valid."
}
```

---

### 3. Logout

Hapus semua token milik employee yang sedang login.

| Properti | Detail                              |
|----------|-------------------------------------|
| Method   | `POST`                              |
| URL      | `/api/mobile/employee/auth/logout`  |
| Auth     | **Wajib** — Bearer Token            |

**Request Header:**

```
Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz1234567890
```

**Response Sukses (200):**

```json
{
  "success": true,
  "message": "Logout berhasil."
}
```

**Response Error — Token Expired (401):**

```json
{
  "success": false,
  "message": "Access token sudah expired. Gunakan refresh token untuk memperbarui.",
  "code": "ACCESS_TOKEN_EXPIRED"
}
```

---

### 4. Data User (Me)

Ambil data employee yang sedang login beserta informasi role-nya.

| Properti | Detail                            |
|----------|-----------------------------------|
| Method   | `GET`                             |
| URL      | `/api/mobile/employee/auth/me`    |
| Auth     | **Wajib** — Bearer Token          |

**Request Header:**

```
Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz1234567890
```

**Response Sukses (200):**

```json
{
  "success": true,
  "data": {
    "id": 10,
    "eci": "EC-0010",
    "name": "Budi Santoso",
    "email": "budi@perusahaan.com",
    "phone": "08123456789",
    "position": "Software Engineer",
    "department": "IT",
    "role": {
      "id": 2,
      "name": "Staff"
    }
  }
}
```

---

## Kode Error

| HTTP | `code`                  | Penjelasan                                           |
|------|-------------------------|------------------------------------------------------|
| 401  | `UNAUTHENTICATED`       | Tidak ada token di header Authorization              |
| 401  | `INVALID_TOKEN`         | Token tidak valid / tidak ditemukan di database      |
| 401  | `INVALID_TOKEN_TYPE`    | Mengirim refresh token ke endpoint yang butuh access token |
| 401  | `ACCESS_TOKEN_EXPIRED`  | Access token kedaluwarsa, gunakan refresh token      |
| 401  | `REFRESH_TOKEN_EXPIRED` | Refresh token kedaluwarsa, harus login ulang         |
| 403  | `NOT_EMPLOYEE`          | User bukan employee (misal: customer)                |
| 403  | `ACCOUNT_INACTIVE`      | Akun dinonaktifkan oleh admin                        |

---

## Panduan Testing Postman

### Langkah 1 — Buat Environment

Buat Postman Environment baru dengan variabel berikut:

| Variable                   | Initial Value               |
|----------------------------|-----------------------------|
| `base_url`                 | `http://localhost:8000/api` |
| `employee_access_token`    | _(kosongkan)_               |
| `employee_refresh_token`   | _(kosongkan)_               |

---

### Langkah 2 — Login sebagai Employee

1. Buat request: `POST {{base_url}}/mobile/employee/auth/login`
2. Tab **Body** → `raw` → `JSON`
3. Isi body:
   ```json
   {
     "email": "budi@perusahaan.com",
     "password": "password123"
   }
   ```
4. Klik **Send**
5. Di tab **Tests**, simpan token otomatis:
   ```javascript
   const res = pm.response.json();
   if (res.success) {
     pm.environment.set("employee_access_token", res.data.access_token);
     pm.environment.set("employee_refresh_token", res.data.refresh_token);
     console.log("Login berhasil! Token tersimpan.");
   }
   ```

---

### Langkah 3 — Test Login sebagai Non-Employee (Harus Ditolak)

1. Gunakan akun customer untuk login ke endpoint yang sama.
2. Pastikan response adalah **403** dengan `code: "NOT_EMPLOYEE"`.

   ```json
   {
     "success": false,
     "message": "Akses ditolak. Hanya employee yang dapat login melalui aplikasi ini.",
     "code": "NOT_EMPLOYEE"
   }
   ```

---

### Langkah 4 — Gunakan Access Token di Endpoint Protected

1. Buat request: `GET {{base_url}}/mobile/employee/auth/me`
2. Tab **Auth** → `Bearer Token` → isi `{{employee_access_token}}`
3. Klik **Send** → data employee tampil

---

### Langkah 5 — Refresh Token

Saat access token expired (atau untuk testing):

1. Buat request: `POST {{base_url}}/mobile/employee/auth/refresh`
2. Body JSON:
   ```json
   {
     "refresh_token": "{{employee_refresh_token}}"
   }
   ```
3. Di tab **Tests**, update token baru:
   ```javascript
   const res = pm.response.json();
   if (res.success) {
     pm.environment.set("employee_access_token", res.data.access_token);
     pm.environment.set("employee_refresh_token", res.data.refresh_token);
     console.log("Token berhasil diperbarui!");
   }
   ```

---

### Langkah 6 — Logout

1. Buat request: `POST {{base_url}}/mobile/employee/auth/logout`
2. Tab **Auth** → `Bearer Token` → `{{employee_access_token}}`
3. Klik **Send** → semua token dihapus dari server

---

## Integrasi Flutter

### Dependensi yang Diperlukan

Tambahkan ke `pubspec.yaml`:

```yaml
dependencies:
  flutter_secure_storage: ^9.0.0   # Simpan token terenkripsi
  dio: ^5.4.0                       # HTTP client dengan interceptor
  shared_preferences: ^2.2.0        # Simpan data user non-sensitif
```

---

### Simpan Token (Secure Storage)

```dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class EmployeeTokenStorage {
  static const _storage = FlutterSecureStorage();

  static Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _storage.write(key: 'emp_access_token', value: accessToken);
    await _storage.write(key: 'emp_refresh_token', value: refreshToken);
  }

  static Future<String?> getAccessToken() =>
      _storage.read(key: 'emp_access_token');

  static Future<String?> getRefreshToken() =>
      _storage.read(key: 'emp_refresh_token');

  static Future<void> clearTokens() => _storage.deleteAll();
}
```

---

### HTTP Client dengan Auto Refresh Token (Dio Interceptor)

```dart
import 'package:dio/dio.dart';

class EmployeeApiClient {
  static const String baseUrl =
      'https://yourdomain.com/api/mobile/employee';

  late final Dio dio;

  EmployeeApiClient() {
    dio = Dio(BaseOptions(baseUrl: baseUrl));
    dio.interceptors.add(_EmployeeAuthInterceptor(dio));
  }
}

class _EmployeeAuthInterceptor extends Interceptor {
  final Dio dio;
  bool _isRefreshing = false;

  _EmployeeAuthInterceptor(this.dio);

  @override
  void onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    // Lampirkan access token di setiap request
    final token = await EmployeeTokenStorage.getAccessToken();
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    final statusCode = err.response?.statusCode;
    final code = err.response?.data['code'];

    // Jika 401 ACCESS_TOKEN_EXPIRED → coba refresh otomatis
    if (statusCode == 401 && code == 'ACCESS_TOKEN_EXPIRED' && !_isRefreshing) {
      _isRefreshing = true;
      final refreshed = await _tryRefreshToken();
      _isRefreshing = false;

      if (refreshed) {
        // Ulangi request asal dengan token baru
        final newToken = await EmployeeTokenStorage.getAccessToken();
        err.requestOptions.headers['Authorization'] = 'Bearer $newToken';
        final retryResponse = await dio.fetch(err.requestOptions);
        return handler.resolve(retryResponse);
      } else {
        // Refresh gagal → paksa login ulang
        await EmployeeTokenStorage.clearTokens();
        // TODO: navigasi ke halaman login
        return handler.reject(err);
      }
    }

    handler.next(err);
  }

  Future<bool> _tryRefreshToken() async {
    final refreshToken = await EmployeeTokenStorage.getRefreshToken();
    if (refreshToken == null) return false;

    try {
      final response = await Dio().post(
        '${EmployeeApiClient.baseUrl}/auth/refresh',
        data: {'refresh_token': refreshToken},
      );
      if (response.data['success'] == true) {
        await EmployeeTokenStorage.saveTokens(
          accessToken: response.data['data']['access_token'],
          refreshToken: response.data['data']['refresh_token'],
        );
        return true;
      }
    } catch (_) {}
    return false;
  }
}
```

---

### Service: Login, Logout & Persistent Login

```dart
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

class EmployeeAuthService {
  final _api = EmployeeApiClient();

  /// Login dengan email/ECI/nomor HP + password
  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await _api.dio.post('/auth/login', data: {
        'email': email,
        'password': password,
      });

      final data = response.data['data'];

      // Simpan token di secure storage
      await EmployeeTokenStorage.saveTokens(
        accessToken: data['access_token'],
        refreshToken: data['refresh_token'],
      );

      // Simpan info user (non-sensitif) di SharedPreferences
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('employee_data', jsonEncode(data['user']));

      return data['user'];
    } on DioException catch (e) {
      final message = e.response?.data['message'] ?? 'Login gagal.';
      final code = e.response?.data['code'];
      throw AuthException(message: message, code: code);
    }
  }

  /// Cek apakah employee masih login (persistent login)
  Future<bool> isLoggedIn() async {
    final accessToken = await EmployeeTokenStorage.getAccessToken();
    final refreshToken = await EmployeeTokenStorage.getRefreshToken();
    // Selama refresh token ada, user dianggap login
    // Access token yang expired akan diperbarui otomatis oleh interceptor
    return accessToken != null && refreshToken != null;
  }

  /// Ambil data user dari cache lokal
  Future<Map<String, dynamic>?> getCachedUser() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('employee_data');
    if (raw == null) return null;
    return jsonDecode(raw) as Map<String, dynamic>;
  }

  /// Logout — hapus token di server dan lokal
  Future<void> logout() async {
    try {
      await _api.dio.post('/auth/logout');
    } catch (_) {
      // Tetap bersihkan token lokal meski request gagal
    } finally {
      await EmployeeTokenStorage.clearTokens();
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('employee_data');
    }
  }
}

class AuthException implements Exception {
  final String message;
  final String? code;
  AuthException({required this.message, this.code});
}
```

---

### Splash Screen — Tentukan Halaman Awal

```dart
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  final _authService = EmployeeAuthService();

  @override
  void initState() {
    super.initState();
    _checkLoginStatus();
  }

  Future<void> _checkLoginStatus() async {
    final loggedIn = await _authService.isLoggedIn();

    if (!mounted) return;

    if (loggedIn) {
      // Refresh token masih ada → langsung ke Home
      // Access token yang expired akan diperbarui otomatis di background
      Navigator.pushReplacementNamed(context, '/home');
    } else {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
```

---

### Contoh Halaman Login

```dart
class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _authService = EmployeeAuthService();
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  bool _loading = false;
  String? _errorMsg;

  Future<void> _login() async {
    setState(() { _loading = true; _errorMsg = null; });

    try {
      await _authService.login(_emailCtrl.text, _passCtrl.text);
      if (!mounted) return;
      Navigator.pushReplacementNamed(context, '/home');
    } on AuthException catch (e) {
      setState(() {
        _errorMsg = e.code == 'NOT_EMPLOYEE'
            ? 'Akses ditolak. Anda bukan employee.'
            : e.message;
      });
    } finally {
      setState(() { _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            TextField(controller: _emailCtrl, decoration: const InputDecoration(labelText: 'Email / ECI')),
            TextField(controller: _passCtrl, obscureText: true, decoration: const InputDecoration(labelText: 'Password')),
            if (_errorMsg != null)
              Text(_errorMsg!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            _loading
                ? const CircularProgressIndicator()
                : ElevatedButton(onPressed: _login, child: const Text('Login')),
          ],
        ),
      ),
    );
  }
}
```

> **Perilaku seperti Instagram:** Selama refresh token belum expired (7 hari), employee tidak perlu login ulang. Jika refresh token expired, baru diarahkan ke halaman login.
