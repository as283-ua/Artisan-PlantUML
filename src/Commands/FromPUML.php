<?php

namespace As283\ArtisanPlantuml\Commands;

use As283\ArtisanPlantuml\Core\MigrationWriter;
use As283\ArtisanPlantuml\Core\ModelWriter;
use As283\ArtisanPlantuml\Exceptions\CycleException;
use As283\ArtisanPlantuml\Util\SchemaUtil;
use As283\PlantUmlProcessor\Exceptions\FieldException;
use As283\PlantUmlProcessor\Model\Cardinality;
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
    {--path-migrations=database/migrations}
    {--path-models=app/Models}
    {--no-models}
    {--no-migrations}
    {--use-composite-keys}';


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
        try {
            $pumlFile = fopen($this->argument('file'), "r");
        } catch (\Exception $e) {
            $this->error("File " . $this->argument('file') . " not found.");
            return 1;
        }

        $puml = fread($pumlFile, filesize($this->argument('file')));
        fclose($pumlFile);

        try {
            $schema = PlantUmlProcessor::parse($puml);
        } catch (FieldException $e) {
            $this->error("Parsing error. " . $e->getMessage());
            return 1;
        }

        if ($schema == null) {
            $this->error("Parsing error. Invalid PlantUML file. You can check what the problem is using the online PlantUML web server: https://www.plantuml.com/plantuml/uml");
            return 1;
        }

        $this->info("Generating models and migrations:");
        try {
            $res = SchemaUtil::orderClasses($schema);
            print_r($schema->relations);
            $classNamesOrdered = $res[0];
            $missingRelations = $res[1];
        } catch (CycleException $e) {
            $this->error("Found cycle in class diagram. Extra migrations will be created for foreign keys.");
            // for now exit code 1
            return 1;
        }

        $i = 1;
        foreach ($classNamesOrdered as $className) {
            if (!$this->option('no-migrations')) {
                MigrationWriter::write($className, $schema, $i, $this);
            }

            if (!$this->option('no-models')) {
                ModelWriter::write($className, $schema, $this);
            }
            $i++;
        }

        // Write junction tables
        if (!$this->option('no-migrations')) {
            foreach ($schema->relations as $i => $relation) {
                if (
                    ($relation->from[1] === Cardinality::Any || $relation->from[1] === Cardinality::AtLeastOne)
                    &&
                    ($relation->to[1] === Cardinality::Any || $relation->to[1] === Cardinality::AtLeastOne)
                ) {
                    MigrationWriter::writeJunctionTable($relation->from[0], $relation->to[0], $schema, $i, $this);
                }
            }
        }

        // Write missing relations
        if (!$this->option('no-migrations')) {
            foreach ($missingRelations as $relation) {
                MigrationWriter::writeMissingRelation($relation, $schema, $this);
            }
        }

        $this->info("Done.");
    }
}
