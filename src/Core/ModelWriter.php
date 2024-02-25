<?php

namespace As283\ArtisanPlantuml\Core;

use As283\PlantUmlProcessor\Model\ClassMetadata;

class ModelWriter
{
    /**
     * Generate a file name for the model
     * @param string $className
     * @return string
     */
    private static function fileName($className)
    {
        return ucfirst(strtolower($className)) . ".php";
    }


    /**
     * Write a migration file for the given class
     * @param ClassMetadata $class
     * @return void
     */
    public static function write($class, $schema, $command)
    {
        // $path = "app/Models";

        // // Remove trailing slash
        // if($path[-1] == "/"){
        //     $path = substr($path, 0, -1);
        // }

        // $migrationFile = $path . "/" . self::fileName($class->name);

        // $migration = fopen($migrationFile, "w");
    }
}