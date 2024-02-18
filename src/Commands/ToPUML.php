<?php

namespace As283\ArtisanPlantuml\Commands;

use Illuminate\Console\Command;
use As283\PlantUmlProcessor\PlantUmlProcessor;

class ToPUML extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 
    'make:to-puml
    {file? : The output filename for the PlantUML class diagram.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected function promptForMissingArgumentsUsing()
    {
        return [
            "file" => $this->ask("What is the output filename for the PlantUML class diagram?")
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        echo "AAAAAAAAAAAAA" . $this->argument('file');
    }
}
