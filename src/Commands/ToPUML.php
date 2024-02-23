<?php

namespace As283\ArtisanPlantuml\Commands;

use Illuminate\Console\Command;
use As283\PlantUmlProcessor\PlantUmlProcessor;
use Illuminate\Contracts\Console\PromptsForMissingInput;

class ToPUML extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 
    'make:to-puml
    {file : The output filename for the PlantUML class diagram.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a .puml file from database migrations and models';

    protected function promptForMissingArgumentsUsing()
    {
        return [
            "file" => "What is the output filename for the PlantUML class diagram?"
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if(file_exists($this->argument('file'))){
            $overwrite = $this->choice("File " . $this->argument('file') . " already exists. Overwrite? (y/n)", ["y", "n"], 1);
            if($overwrite == "n"){
                $this->info("Cancelling operation.");
                return 0;
            }
        }
    }
}
