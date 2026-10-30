<?php

namespace App\Providers;

use App\Services\Guide\DatabaseTopicOverrides;
use App\Services\Guide\TopicOverrides;
use Illuminate\Support\ServiceProvider;

/** Menyambungkan hasil sunting admin (database) ke mesin User Guide. */
class GuideServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TopicOverrides::class, DatabaseTopicOverrides::class);
    }
}
