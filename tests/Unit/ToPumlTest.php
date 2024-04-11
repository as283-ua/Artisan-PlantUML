<?php

namespace As283\ArtisanPlantuml\Tests;

use As283\ArtisanPlantuml\Commands\ToPUML;
use Orchestra\Testbench\TestCase;

class ToPumlTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['As283\ArtisanPlantuml\PUMLServiceProvider'];
    }

    public function testCallCommand()
    {
        $this->artisan("make:to-puml tests/Unit/Resources/out/diagrams/nonexistentfile.puml")->assertExitCode(0);
    }

    public function testAsksFilename()
    {
        $this->artisan("make:to-puml")->expectsQuestion("What is the output filename for the PlantUML class diagram?", "nonexistentfile.puml")->assertExitCode(0);
    }

    public function testToPumlGeneral()
    {
        $this->artisan("make:to-puml tests/Unit/Resources/out/diagrams/diagram.puml --path=tests/Unit/Resources/migrations/full")->assertExitCode(0);
    }

    public function testToPumlOneOne()
    {
        $this->artisan("make:to-puml tests/Unit/Resources/out/diagrams/diagram.puml --path=tests/Unit/Resources/migrations/oneone")->assertExitCode(0);
    }

    public function testToPumlCycle()
    {
        $this->artisan("make:to-puml tests/Unit/Resources/out/diagrams/diagram.puml --path=tests/Unit/Resources/migrations/cycle")->assertExitCode(0);
    }

    public function testToPumlModifying()
    {
        $this->artisan("make:to-puml tests/Unit/Resources/out/diagrams/diagram.puml --path=tests/Unit/Resources/migrations/modifying")->assertExitCode(0);
    }
}
