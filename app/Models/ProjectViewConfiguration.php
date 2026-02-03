<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectViewConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'default_view',
        'gantt_settings',
        'table_settings',
        'column_visibility',
    ];

    protected $casts = [
        'gantt_settings' => 'array',
        'table_settings' => 'array',
        'column_visibility' => 'array',
    ];

    // Default settings
    protected $attributes = [
        'default_view' => 'table',
        'gantt_settings' => '{}',
        'table_settings' => '{}',
        'column_visibility' => '{}',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Get Gantt settings with defaults
    public function getGanttSettingsWithDefaults()
    {
        return array_merge([
            'view_mode' => 'Month',
            'show_dependencies' => true,
            'show_progress' => true,
            'show_critical_path' => false,
            'enable_drag_drop' => true,
            'enable_zoom' => true,
            'min_column_width' => 50,
            'row_height' => 40,
            'bar_height' => 20,
            'padding' => 18,
            'date_format' => 'YYYY-MM-DD',
            'weekend_highlight' => true,
            'today_marker' => true,
        ], $this->gantt_settings ?? []);
    }

    // Get table settings with defaults
    public function getTableSettingsWithDefaults()
    {
        return array_merge([
            'rows_per_page' => 25,
            'show_filters' => true,
            'show_search' => true,
            'sticky_headers' => true,
            'expandable_groups' => true,
            'zebra_striping' => true,
            'dense_mode' => false,
            'show_phase_summary' => true,
            'editable_inline' => false,
        ], $this->table_settings ?? []);
    }

    // Get column visibility with defaults
    public function getColumnVisibilityWithDefaults()
    {
        return array_merge([
            'task_title' => true,
            'module' => true,
            'new_req' => true,
            'tcode' => true,
            'receive_type' => true,
            'complexity' => true,
            'functional_sinergi' => true,
            'technical_sinergi' => true,
            'planned_start' => true,
            'planned_end' => true,
            'planned_days' => true,
            'actual_start' => true,
            'actual_end' => true,
            'actual_days' => true,
            'status' => true,
            'progress' => true,
            'deliverable' => true,
            'notes' => true,
            'assigned_to' => false,
            'priority' => false,
            'dependencies' => false,
        ], $this->column_visibility ?? []);
    }

    // Update specific setting
    public function updateSetting($type, $key, $value)
    {
        $settings = $this->{$type . '_settings'} ?? [];
        $settings[$key] = $value;
        $this->{$type . '_settings'} = $settings;
        $this->save();
    }

    // Toggle column visibility
    public function toggleColumnVisibility($column)
    {
        $visibility = $this->column_visibility ?? [];
        $visibility[$column] = !($visibility[$column] ?? false);
        $this->column_visibility = $visibility;
        $this->save();
    }
}

class ProjectPhaseTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'template_name',
        'phase_configuration',
        'is_active',
    ];

    protected $casts = [
        'phase_configuration' => 'array',
        'is_active' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function applyToProject(Project $project)
    {
        DB::transaction(function () use ($project) {
            // Clear existing phase configuration
            DB::table('project_project_phase')->where('project_id', $project->id)->delete();
            
            // Apply template configuration
            foreach ($this->phase_configuration as $config) {
                DB::table('project_project_phase')->insert([
                    'project_id' => $project->id,
                    'project_phase_id' => $config['phase_id'],
                    'weight' => $config['weight'],
                    'order_sequence' => $config['order_sequence'],
                    'is_visible' => $config['is_visible'],
                    'orientation' => $config['orientation'],
                    'custom_settings' => json_encode($config['custom_settings'] ?? []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    // Duplicate template
    public function duplicate($newName = null)
    {
        $newTemplate = $this->replicate();
        $newTemplate->template_name = $newName ?? $this->template_name . ' (Copy)';
        $newTemplate->save();
        return $newTemplate;
    }
}