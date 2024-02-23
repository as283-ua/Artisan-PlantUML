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

    public function testAsksFilename()
    {
        $this->
        artisan("make:to-puml")->
        expectsQuestion("What is the output filename for the PlantUML class diagram?", "nonexistentfile.puml")->
        assertExitCode(0);
    }
}
