<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DeliveryProjectPlanController extends Controller
{
    /**
     * Menampilkan daftar semua project untuk memilih.
     * Ini akan menjadi halaman utama untuk "Project Plan UI".
     */
    public function index()
    {
        $projects = DeliveryProject::latest()->paginate(10);
        return view('project-planning.index', compact('projects'));
    }

    /**
     * Menampilkan form untuk mengisi timeline plan.
     * Ini adalah halaman untuk "Project Planning".
     */
    public function create(DeliveryProject $project)
    {
        // Ambil aktivitas yang sudah ada atau siapkan array kosong
        $existing_activities = $project->activities()
            ->get()
            ->keyBy(function($item) {
                // Buat key unik untuk memudahkan pencarian di view
                return $item->phase . '_' . str_replace(' ', '_', $item->activity_name);
            });

        return view('project-planning.create', [
            'project' => $project,
            'activities_structure' => $this->activities_structure,
            'existing_activities' => $existing_activities
        ]);
    }
}