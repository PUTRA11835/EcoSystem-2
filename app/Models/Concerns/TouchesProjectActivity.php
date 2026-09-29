<?php

namespace App\Models\Concerns;

use App\Models\DeliveryProject;

/**
 * Setiap create / update / delete pada record anak sebuah Delivery Project
 * (Issue, Risk, WRICEF, Cost, Document, Planning, dst.) memperbarui
 * delivery_projects.last_activity_at — kolom "Last Update Date" di list project.
 *
 * Hanya jalur yang memicu event Eloquent yang tertangkap: saveQuietly(),
 * query builder (::where()->update()/delete(), DB::table()) sengaja TIDAK
 * dihitung karena di kode ini dipakai untuk sinkronisasi/rekalkulasi otomatis.
 * Aksi user yang memang lewat query builder memanggil
 * DeliveryProject::markActivity() secara eksplisit di controller-nya.
 *
 * Model pemakai boleh meng-override projectIdForActivity() bila FK project-nya
 * tidak tersimpan langsung di kolom delivery_projects_id.
 */
trait TouchesProjectActivity
{
    public static function bootTouchesProjectActivity(): void
    {
        static::created(fn ($model) => $model->markProjectActivity());
        static::updated(fn ($model) => $model->markProjectActivity());
        static::deleted(fn ($model) => $model->markProjectActivity());
    }

    protected function markProjectActivity(): void
    {
        DeliveryProject::markActivity($this->projectIdForActivity());
    }

    protected function projectIdForActivity()
    {
        return $this->getAttribute('delivery_projects_id');
    }
}
