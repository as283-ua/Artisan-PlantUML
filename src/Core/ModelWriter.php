<?php

namespace As283\ArtisanPlantuml\Core;

use As283\ArtisanPlantuml\Util\SchemaUtil;
use As283\PlantUmlProcessor\Model\Cardinality;
use As283\PlantUmlProcessor\Model\Schema;
use Illuminate\Console\Command;
use Illuminate\Support\Pluralizer;

class ModelWriter
{
    /**
     * Get the model name with the correct case
     * @param string $className
     * @return string
     */
    private static function modelName($className)
    {
        return ucfirst(strtolower($className));
    }

    /**
     * Write the use statements for the model file
     * @param resource $file
     * @param string $modelName
     * @param Command $command
     * @return void
     */
    private static function writeUseStatements($file, $modelName, &$command)
    {
        fwrite($file, "<?php\n\n");
        fwrite($file, "namespace App\Models;\n");
        fwrite($file, "use Illuminate\Database\Eloquent\Model;\n\n");
        fwrite($file, "class " .  $modelName. " extends Model\n");
        fwrite($file, "{\n");
    }

    /**
     * Get cardinality (hasOne, hasMany, belongsTo, belongsToMany) for Eloquent model specified by $cardinality1
     * @param Cardinality $cardinality1
     * @param Cardinality $cardinality2
     * @param string $className1
     * @param string $className2
     * @return string
     */
    private static function getEloquentCardinality($cardinality1, $cardinality2, $className1, $className2){
        if(in_array($cardinality1, [Cardinality::Any, Cardinality::AtLeastOne]) 
            && in_array($cardinality2, [Cardinality::Any, Cardinality::AtLeastOne])){
            return "belongsToMany";
        }

        if(in_array($cardinality1, [Cardinality::Any, Cardinality::AtLeastOne]) 
            && in_array($cardinality2, [Cardinality::One, Cardinality::ZeroOrOne])){
            return "hasMany";
        }

        if(in_array($cardinality1, [Cardinality::One, Cardinality::ZeroOrOne]) 
            && in_array($cardinality2, [Cardinality::Any, Cardinality::AtLeastOne])){
            return "belongsTo";
        }

        if(in_array($cardinality1, [Cardinality::One, Cardinality::ZeroOrOne]) 
            && in_array($cardinality2, [Cardinality::One, Cardinality::ZeroOrOne])){
            // FK is in the class alphabetically first
            if($className1 < $className2){
                return "belongsTo";
            } else {
                return "hasOne";
            }
        }
        echo "\n1. ";
        print_r($cardinality1);

        echo "\n2. ";
        print_r($cardinality2);
    }

    /**
     * Write the body of the model file
     * @param resource $file
     * @param string $className
     * @param Schema $schema
     * @param Command $command
     * @return void
     */
    private static function writeBody($file, $className, &$schema, &$command)
    {
        $class = $schema->classes[$className];
        $keys = array_keyS(SchemaUtil::classKeys($class));

        // Set primary key if not id
        if($keys[0] != "id"){
            fwrite($file, "    protected \$primaryKey = '" . $keys[0] . "';\n\n");
        }

        foreach ($class->relatedClasses as $relatedClassName => $indexes) {
            $relation = $schema->relations[$indexes[0]];
            $relatedClass = $schema->classes[$relatedClassName];
            $relatedKeys = array_keys(SchemaUtil::classKeys($relatedClass));

            $cardinality = SchemaUtil::getCardinality($className, $relation);
            $otherCardinality = SchemaUtil::getCardinality($relatedClassName, $relation);
            
            $eloquentCardinality = self::getEloquentCardinality($cardinality, $otherCardinality, $className, $relatedClassName);
            
            if($eloquentCardinality !== "belongsToMany"){
                $modelContainsFK = $eloquentCardinality === "belongsTo";
                $foreignKey = $modelContainsFK ? strtolower($relatedClassName) . "_" . $relatedKeys[0] : strtolower($className) . "_" . $keys[0];
                $primaryKey = $modelContainsFK ? $relatedKeys[0] : $keys[0];
    
                if($relatedKeys[0] != "id"){
                    fwrite($file, "    public function " . Pluralizer::plural(strtolower($relatedClass->name)) . "()\n");
                    fwrite($file, "    {\n");
                    fwrite($file, "        return \$this->" . $eloquentCardinality . "(" . self::modelName($relatedClass->name) . "::class, '" . $foreignKey . "', '" . $primaryKey . "');\n");
                    fwrite($file, "    }\n\n");
                } else {
                    fwrite($file, "    public function " . Pluralizer::plural(strtolower($relatedClass->name)) . "()\n");
                    fwrite($file, "    {\n");
                    fwrite($file, "        return \$this->" . $eloquentCardinality . "(" . self::modelName($relatedClass->name) . "::class);\n");
                    fwrite($file, "    }\n\n");
                }
            } else {
                $fkSelf = strtolower($className) . "_" . $keys[0];
                $fkRelated = strtolower($relatedClassName) . "_" . $relatedKeys[0];
                fwrite($file, "    public function " . Pluralizer::plural(strtolower($relatedClass->name)) . "()\n");
                fwrite($file, "    {\n");
                fwrite($file, "        return \$this->belongsToMany(" . self::modelName($relatedClass->name) . "::class, null, '" . $fkSelf . "', '" . $fkRelated . "');\n");
                fwrite($file, "    }\n\n");
            }
        }

        fwrite($file, "}\n");
    }

    /**
     * Write a migration file for the given class
     * @param string $class
     * @param Schema $schema
     * @param Command &$command
     * @return void
     */
    public static function write($class, &$schema, &$command)
    {
        $path = $command->option('path-models');

        // Remove trailing slash
        if($path[-1] == "/"){
            $path = substr($path, 0, -1);
        }

        $modelName = self::modelName($class);
        $modelFile = $path . "/" . $modelName . ".php";

        $modelFile = fopen($modelFile, "w");

        self::writeUseStatements($modelFile, $modelName, $command);
        self::writeBody($modelFile, $class, $schema, $command);
    }
}