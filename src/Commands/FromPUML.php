<?php

namespace As283\ArtisanPlantuml\Commands;

use Illuminate\Console\Command;
use As283\PlantUmlProcessor\PlantUmlProcessor;

class FromPUML extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 
    'make:from-puml
    {file? : The filename of the PlantUML class diagram.}';

    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate migrations and models from PlantUML file.';
    
    protected function promptForMissingArgumentsUsing()
    {
        return [
            "file" => $this->ask("What is the filename of the PlantUML class diagram?")
        ];
    }


    /**
     * Execute the console command.
     */
    public function handle()
    {
        echo "AAAAAAAAAAAAA" . $this->argument('file');
        $pumlFile = fopen($this->argument('file'), "r") or die("Unable to open file!");
        $puml = fread($pumlFile,filesize($this->argument('file')));

        $schema = PlantUmlProcessor::parse($puml);

        echo $schema;
    }
}
