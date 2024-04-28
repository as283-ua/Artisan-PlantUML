<?php

namespace As283\ArtisanPlantuml\Commands;

use As283\ArtisanPlantuml\Core\MigrationParser;
use As283\PlantUmlProcessor\Model\Schema;
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
    {file : The output filename for the PlantUML class diagram.}
    {--force=false : Overwrite the file if it already exists.}
    {--path=database/migrations : Path to migrations.}
    {--ignore-default-migration=true : Ignore the migrations that create the users, password reset, failed jobs and personal access tokens tables}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a .puml file from database migrations and models';

    private $defaultMigrations = [
        "2014_10_12_000000_create_users_table.php",
        "2014_10_12_100000_create_password_reset_tokens_table.php",
        "2019_08_19_000000_create_failed_jobs_table.php",
        "2019_12_14_000001_create_personal_access_tokens_table.php"
    ];

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
        if (!$this->option("force") && file_exists($this->argument('file'))) {
            $overwrite = $this->choice("File " . $this->argument('file') . " already exists. Overwrite? (y/n)", ["y", "n"], 1);
            if ($overwrite == "n") {
                $this->info("Cancelling operation.");
                return 0;
            }
        }

        $schema = new Schema();
        $migrationParser = new MigrationParser();

        foreach (glob($this->option("path") . "/*.php") as $migrationFile) {
            if ($this->option("ignore-default-migration") && in_array(basename($migrationFile), $this->defaultMigrations)) {
                continue;
            }

            $content = file_get_contents($migrationFile);
            $content = self::getUsefulContent($content);
            $migrationParser->parse($content, $schema);
            // echo "after {$migrationFile}: \n";s
            // print_r($schema);
        }

        $f = fopen($this->argument("file"), "w");

        fwrite($f, PlantUmlProcessor::serialize($schema));
        fclose($f);
    }

    /**
     * @param string $migration Migration text
     * @return string Content inside table definition in up() 
     */
    private static function getUsefulContent($migration)
    {
        $startDef = strpos($migration, "Schema::");
        $migration = substr($migration, $startDef);

        $startDef = 0;

        if (substr($migration, $startDef, 12) === "Schema::drop") {
            $endDef = strpos($migration, ";") + 1;
        } else {
            // creating and modifying a table has an inner function
            $endDef = strpos($migration, "});") + 3;
        }

        return substr($migration, $startDef, $endDef - $startDef);
    }
}
