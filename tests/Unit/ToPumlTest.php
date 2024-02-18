<?php
namespace As283\ArtisanPlantuml\Tests;

use Orchestra\Testbench\TestCase;

class ToPumlTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['As283\ArtisanPlantuml\PUMLServiceProvider'];
    }

    public function testParse()
    {
        $this->artisan("make:to-puml")->expectsQuestion("What is the output filename for the PlantUML class diagram?", "test.puml")->assertExitCode(1);
    }
}
