<?php

namespace As283\ArtisanPlantuml\Commands;

use As283\ArtisanPlantuml\Core\MigrationWriter;
use As283\ArtisanPlantuml\Core\ModelWriter;
use As283\PlantUmlProcessor\Exceptions\FieldException;
use Illuminate\Console\Command;
use As283\PlantUmlProcessor\PlantUmlProcessor;
use Illuminate\Contracts\Console\PromptsForMissingInput;

class FromPUML extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 
    'make:from-puml
    {file : The filename of the PlantUML class diagram.}
    {--force}
    {--path=database/migrations}';

    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate migrations and models from PlantUML file';
    
    protected function promptForMissingArgumentsUsing()
    {
        return [
            "file" => "What is the filename of the PlantUML class diagram?"
        ];
    }


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pumlFile = null;
        try{
            $pumlFile = fopen($this->argument('file'), "r");
        } catch (\Exception $e) {
            $this->error("File " . $this->argument('file') . " not found.");
            return 1;
        }

        $puml = fread($pumlFile,filesize($this->argument('file')));
        fclose($pumlFile);

        try{
            $schema = PlantUmlProcessor::parse($puml);
        } catch (FieldException $e){
            $this->error("Parsing error. " . $e->getMessage());
            return 1;
        }

        if($schema == null){
            $this->error("Parsing error. Invalid PlantUML file. You can what the problem is using the online PlantUML web server: https://www.plantuml.com/plantuml/uml");
            return 1;
        }

        $this->info("Generating models and migrations:");
        
        foreach ($schema->classes as $class) {
            MigrationWriter::writeCreateMigrations($class, $schema, $this);
            ModelWriter::write($class, $schema, $this);
        }

        $this->info($puml);
    }
}
