<?php

namespace As283\ArtisanPlantuml;

use Illuminate\Console\Command;
use As283\PlantUmlProcessor\PlantUmlProcessor;

class FromPUML extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:from-puml {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate migrations and models from PlantUML file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pumlFile = fopen($this->argument('file'), "r") or die("Unable to open file!");
        $puml = fread($pumlFile,filesize($this->argument('file')));

        $schema = PlantUmlProcessor::parse($puml);

        echo $schema;
    }
}
