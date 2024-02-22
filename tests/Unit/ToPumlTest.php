<?php
namespace As283\ArtisanPlantuml\Tests;

use Orchestra\Testbench\TestCase;

class ToPumlTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['As283\ArtisanPlantuml\PUMLServiceProvider'];
    }

    public function testCallCommand()
    {
        $this->
        artisan("make:to-puml nonexistentfile.puml")->
        assertExitCode(0);
    }
}
