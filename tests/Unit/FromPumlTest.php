<?php
namespace As283\ArtisanPlantuml\Tests;

use Orchestra\Testbench\TestCase;

class FromPumlTest extends TestCase
{
    const OUT_DIR = "tests/Unit/Resources/out";

    protected function getPackageProviders($app)
    {
        return ['As283\ArtisanPlantuml\PUMLServiceProvider'];
    }

    private function cleanOutFiles(){
        $files = glob(self::OUT_DIR . "/*");
        foreach($files as $file){
            if(is_file($file))
                unlink($file);
        }
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

    public function testCreatesMigrationFile()
    {
        $this->cleanOutFiles();
        
        $this->
        artisan("make:from-puml tests/Unit/Resources/address.puml --path=" . self::OUT_DIR)->
        assertExitCode(0);
        
        $migration = glob(self::OUT_DIR . "/*_create_addresses_table.php")[0];

        $this->assertNotNull($migration);
    }

    public function testMigrationWithConstraints()
    {
        $this->cleanOutFiles();

        $this->
        artisan("make:from-puml tests/Unit/Resources/address2.puml --path=" . self::OUT_DIR)->
        assertExitCode(0);
        
        $migration = glob(self::OUT_DIR . "/*_create_addresses_table.php")[0];

        $this->assertNotNull($migration);
    }

    public function testMigrationBig()
    {
        $this->cleanOutFiles();

        $this->
        artisan("make:from-puml tests/Unit/Resources/diagramaEjemplo.puml --path=" . self::OUT_DIR)->
        assertExitCode(0);
        
        $migrationCount = count(glob(self::OUT_DIR . "/*_create_*.php"));

        $this->assertEquals(8, $migrationCount);
    }

    public function testMigrationRepeatedFk()
    {
        $this->cleanOutFiles();

        $this->
        artisan("make:from-puml tests/Unit/Resources/doubleFk.puml --path=" . self::OUT_DIR)->
        assertExitCode(0);
        
        $migrationCount = count(glob(self::OUT_DIR . "/*_create_*.php"));

        $this->assertEquals(2, $migrationCount);
    }

    public function testMigrationOneOneRelations()
    {
        $this->cleanOutFiles();

        $this->
        artisan("make:from-puml tests/Unit/Resources/relationTest.puml --path=" . self::OUT_DIR)->
        assertExitCode(0);
        
        $migrationCount = count(glob(self::OUT_DIR . "/*_create_*.php"));

        $this->assertEquals(3, $migrationCount);
    }
}
