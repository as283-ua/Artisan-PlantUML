<?php

namespace As283\ArtisanPlantuml;

use Illuminate\Support\ServiceProvider;
use As283\ArtisanPlantuml\FromPUML;

class PUMLServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FromPUML::class,
                ToPUML::class
            ]);
        }
    }
}