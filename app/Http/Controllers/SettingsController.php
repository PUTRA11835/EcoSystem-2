<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = session('user');
            $token = session('auth_token');

            if (!$user || !$token) {
                return redirect()->route('login')->withErrors(['message' => 'Please login first']);
            }

            // Get user preferences from session or default
            $userPreferences = session('user_preferences', [
                'theme' => 'light',
                'primary_color' => '#c62828',
                'sidebar_style' => 'gradient',
                'font_size' => 'medium',
                'compact_mode' => false,
                'show_animations' => true,
                'language' => 'en',
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'notifications_enabled' => true,
                'email_notifications' => true,
                'push_notifications' => false,
            ]);

            Log::info('Settings page accessed', [
                'user_id' => $user['id'],
                'user_name' => $user['name'] ?? $user['company_name'] ?? 'Unknown',
            ]);

            return view('settings.index', [
                'user' => $user,
                'preferences' => $userPreferences,
            ]);

        } catch (\Exception $e) {
            Log::error('Settings page error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

            $preferences = $request->all();
            
            // Save to session (in production, save to database)
            session(['user_preferences' => $preferences]);

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

            // Reset to default preferences
            $defaultPreferences = [
                'theme' => 'light',
                'primary_color' => '#c62828',
                'sidebar_style' => 'gradient',
                'font_size' => 'medium',
                'compact_mode' => false,
                'show_animations' => true,
                'language' => 'en',
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'notifications_enabled' => true,
                'email_notifications' => true,
                'push_notifications' => false,
            ];

            session(['user_preferences' => $defaultPreferences]);

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
}