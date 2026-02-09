<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectUpdate; // Model ini kita gunakan untuk isu
use Illuminate\Http\Request;

class DeliveryProjectIssueController extends Controller
{
    /**
     * Menampilkan daftar semua isu dari semua proyek, yang terbaru di atas.
     */
    public function index()
    {
        // LOGIKA BARU:
        // 1. Buat subquery untuk mendapatkan ID dari isu terbaru untuk setiap project_id.
        //    Kita asumsikan ID tertinggi adalah yang terbaru.
        // 2. Ambil hanya data isu yang ID-nya ada di dalam hasil subquery tersebut.
        $issues = DeliveryProjectUpdate::whereIn('id', function($query) {
                $query->selectRaw('max(id)')
                    ->from('delivery_project_updates')
                    ->groupBy('delivery_projects_id');
            })
            ->with(['delivery_project.client.basicData']) // Eager load relasi untuk efisiensi
            ->latest('created_at')     // Urutkan hasil akhir berdasarkan tanggal isu dibuat
            ->get();

        return view('delivery.project.issues.index', compact('issues'));
    }

    /**
     * Menampilkan semua isu yang dimiliki oleh satu proyek spesifik.
     */
    public function show(DeliveryProject $project)
    {
        // Laravel otomatis akan mencari Project berdasarkan ID dari URL (Route Model Binding)
        // Kita hanya perlu memuat relasi 'updates'-nya.
        $project->load('updates');

        return view('delivery.project.issues.show', compact('project'));
    }
}