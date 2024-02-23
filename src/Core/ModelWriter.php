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
        
        return "";
    }

    /**
     * Generate the up method for the migration
     * @param ClassMetadata $class
     * @return string
     */
    private static function writeUp($class)
    {
        return "";
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

        return "Writing migration";
    }
}