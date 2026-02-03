<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{

    public function index(Request $request)
    {
        try {
            $user = session('user');
            $token = session('auth_token');

            Log::info('Dashboard access attempt', [
                'has_user' => $user ? 'yes' : 'no',
                'has_token' => $token ? 'yes' : 'no',
                'ip' => $request->ip()
            ]);

            if (!$user || !$token) {
                Log::warning('Dashboard accessed without valid session', [
                    'ip_address' => $request->ip(),
                ]);
                return redirect()->route('login')->withErrors(['message' => 'Please login first']);
            }

            $dashboardData = [
                'total_employees' => 0,
                'total_customers' => 0,
                'recent_activities' => [],
            ];

            Log::info('Dashboard accessed successfully', [
                'user_id' => $user['id'],
                'user_name' => $user['name'] ?? $user['company_name'] ?? 'Unknown',
                'user_type' => $user['type'],
                'ip_address' => $request->ip(),
            ]);

            return view('home.home', [
                'user' => $user,
                'data' => $dashboardData,
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('login')->withErrors([
                'message' => 'An error occurred. Please login again.'
            ]);
        }
    }
}