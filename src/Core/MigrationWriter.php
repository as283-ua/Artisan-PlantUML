<?php

namespace As283\ArtisanPlantuml\Core;

use Illuminate\Support\Pluralizer;
use As283\PlantUmlProcessor\Model\ClassMetadata;

class MigrationWriter
{
    /**
     * Generate a file name for the migration
     * @param string $className
     * @return string
     */
    private static function fileName($className)
    {
        $table = Pluralizer::plural(strtolower($className));
        return date("Y_m_d_His") . "_create_" . $table . "_table.php";
    }

    /**
     * Generate the up method for the migration
     * @param ClassMetadata $class
     * @return string
     */
    private static function writeUp($class)
    {
        $up = "Schema::create('" . Pluralizer::plural(strtolower($class->name)) . "', function (Blueprint \$table) {\n";
        foreach($class->fields as $field){
            // $up .= "    \$table->" . $field->type . "('" . $field->name . "')";
            // if($field->nullable){
            //     $up .= "->nullable()";
            // }
            // $up .= ";\n";
        }
        $up .= "});\n";
        return $up;
    }

    /**
     * Write a migration file for the given class
     * @param ClassMetadata $class
     * @return void
     */
    public static function write($class, $path = "database/migrations")
    {
        // Remove trailing slash
        if($path[-1] == "/"){
            $path = substr($path, 0, -1);
        }

        $migrationFile = $path . "/" . self::fileName($class->name);

        $migration = fopen($migrationFile, "w");

        fclose($migration);
    }
}