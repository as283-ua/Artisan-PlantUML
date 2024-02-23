<?php
namespace As283\ArtisanPlantuml\Tests;

use Orchestra\Testbench\TestCase;

class FromPumlTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['As283\ArtisanPlantuml\PUMLServiceProvider'];
    }

    public function testCallCommand()
    {
        $this->
        artisan("make:from-puml nonexistentfile.puml")->
        assertExitCode(1);
    }

    public function testAsksFilename()
    {
        $this->
        artisan("make:from-puml")->
        expectsQuestion("What is the filename of the PlantUML class diagram?", "nonexistentfile.puml")->
        assertExitCode(1);
    }
}
