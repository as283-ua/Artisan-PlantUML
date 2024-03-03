<?php

namespace As283\ArtisanPlantuml\Core;

use Illuminate\Support\Pluralizer;
use As283\PlantUmlProcessor\Model\ClassMetadata;
use As283\PlantUmlProcessor\Model\Field;
use As283\PlantUmlProcessor\Model\Schema;
use As283\PlantUmlProcessor\Model\Type;
use As283\PlantUmlProcessor\Model\Cardinality;
use As283\PlantUmlProcessor\Model\Relation;
use Illuminate\Console\Command;

class MigrationWriter
{
    /**
     * Generate a file name for the migration
     * @param string $className
     * @param int $index
     * @return string
     */
    private static function fileName($className, $index)
    {
        $table = Pluralizer::plural(strtolower($className));
        return date("Y_m_d_His") . "_" . $index . "_create_" . $table . "_table.php";
    }

    /**
     * Convert a field type to a Laravel migration type
     * @param Field $fieldType
     * @return string|null
     */
    private static function fieldTypeToLaravelType($fieldType)
    {
        switch ($fieldType) {
            case Type::string:
                return "string";
            case Type::int:
                return "integer";
            case Type::float:
                return "float";
            case Type::double:
                return "double";
            case Type::bool:
                return "boolean";
            case Type::Date:
                return "date";
            case Type::DateTime:
                return "dateTime";
            default:
                return null;
        }
    }


    /**
     * Obtain list of fields that are part of the primary key with their data type. If one of the fields is "id", it is assumed to be the primary key and no further fields are considered. If no field is marked as primary, "id" is assumed to be the primary key. This function always return an array of at least one element.
     * @param ClassMetadata $class
     * @return array<string,Type> List of fields that are part of the primary key . ["id"] if no primary key is defined
     */
    private static function classKeys($class){
        $keys = [];
        foreach($class->fields as $field){
            if($field->name === "id"){
                $field->primary = true;
                // might ignore type and just use id()
                return [$field->name => $field->type];
            } else if($field->primary){
                $keys[$field->name] = $field->type;
            }
        }

        if(sizeof($keys) == 0)
            $keys = ["id" => Type::int];
        return $keys;
    }

    /**
     * Get the cardinality of a relation
     * @param string $className
     * @param Relation $relation
     * @return Cardinality|null
     */
    private static function getCardinality($className, $relation){
        if($relation->from[0] === $className){
            return $relation->from[1];
        } else if($relation->to[0] === $className){
            return $relation->to[1];
        }

        // this should only happen if it's called incorrectly
        // we call it in a foreach so it should never happen
        return null;
    }
    


    /**
     * Write the use statements for the migration
     * @param resource $file
     * @return void
     */
    private static function writeUseStatements($file)
    {
        fwrite($file, "<?php\n\n");
        fwrite($file, "use Illuminate\Database\Migrations\Migration;\n");
        fwrite($file, "use Illuminate\Database\Schema\Blueprint;\n");
        fwrite($file, "use Illuminate\Support\Facades\Schema;\n\n");
        fwrite($file, "return new class extends Migration\n");
        fwrite($file, "{\n");
        fwrite($file, "    public function up(): void\n");
        fwrite($file, "    {\n");
    }


    /**
     * Generate the up method for the migration
     * @param resource $file
     * @param ClassMetadata $class
     * @param Schema $schema
     * @param Command $command
     * @param bool $usesTimeStamps
     * @return void
     */
    private static function writeUp($file, $class, $schema, $command, $usesTimeStamps = true)
    {
        fwrite($file, "        Schema::create('" . Pluralizer::plural(strtolower($class->name)) . "', function (Blueprint \$table) {\n");
        
        $classPKs = self::classKeys($class);
        $usesId = isset($classPKs["id"]);
        if($usesId){
            fwrite($file, "            \$table->id();\n");
        }

        if($usesTimeStamps){
            fwrite($file, "            \$table->timestamps();\n");
        }
        
        foreach ($class->fields as $field) {
            if($field->name == "id"){
                continue;
            }

            if($field->type == null){
                $command->error("Field " . $field->name . " has no type. Skipping.");
                continue;
            }

            $type = self::fieldTypeToLaravelType($field->type);
            if($type == null){
                $command->error("Field " . $field->name . " has an unsupported type. Skipping.");
                continue;
            }

            fwrite($file, "            \$table->" . $type . "('" . $field->name . "')");
            if (!$field->primary && $field->nullable) {
                fwrite($file, "->nullable()");
            }
            if (!$field->primary && $field->unique) {
                fwrite($file, "->unique()");
            }
            fwrite($file, ";\n");
        }

        // Write primary keys
        if(count($classPKs) > 1){
            fwrite($file, "            \$table->primary([");
            foreach($classPKs as $pk => $ignore){
                fwrite($file, "'" . $pk . "', ");
            }
            fwrite($file, "]);\n");
        } else if(count($classPKs) == 1 && !$usesId){
            $classKeys = array_keys($classPKs);
            
            fwrite($file, "            \$table->primary('" . $classKeys[0] . "');\n");
        }

        // // Write foreign keys
        foreach ($class->relationIndexes as $index => $relatedClassName) {
            $relation = $schema->relations[$index];

            $cardinality = self::getCardinality($class->name, $relation);

            // Cardinality of many goes in another table, skip here
            if($cardinality == Cardinality::Any || $cardinality == Cardinality::AtLeastOne){
                continue;
            }

            $otherCardinality = self::getCardinality($relatedClassName, $relation);
            if($otherCardinality == Cardinality::One || $otherCardinality == Cardinality::ZeroOrOne){
                continue;
            }

            $otherClass = $schema->classes[$relatedClassName];
            $otherClassPKs = self::classKeys($otherClass);
            $otherUsesId = isset($otherClassPKs["id"]);

            if($otherUsesId){
                fwrite($file, "            \$table->foreignId('" . strtolower($relatedClassName) . "_id')->constrained();\n");
            } else {
                foreach ($otherClassPKs as $key => $type) {
                    fwrite($file, "            \$table->" . self::fieldTypeToLaravelType($type) . "('" . strtolower($relatedClassName) . "_" . $key . "');\n");
                    fwrite($file, "            \$table->foreign('" . strtolower($relatedClassName) . "_" . $key . "')->references('" . $key . "')->on('" . strtolower($relatedClassName) . "');\n");
                }
            }

            //no support for many to many
        }

        fwrite($file, "        });\n");
        fwrite($file, "    }\n\n");
    }

    /**
     * Generate the down method for the migration
     * @param ClassMetadata $class
     * @param resource $file
     * @return void
     */
    private static function writeDown($file, $class){
        fwrite($file, "    public function down(): void\n");
        fwrite($file, "    {\n");
        fwrite($file, "        Schema::dropIfExists('" . Pluralizer::plural(strtolower($class->name)) . "');\n");
        fwrite($file, "    }\n");
        fwrite($file, "};");
    }


    /**
     * Write a migration file for the given class
     * @param ClassMetadata $class
     * @param Schema $schema
     * @param int $index
     * @param Command $command. For outputting messages to the console and getting command line parameters and options
     * @return void
     */
    public static function write($class, $schema, $index, $command){
        $path = $command->option("path");

        // Remove trailing slash
        if($path[-1] == "/"){
            $path = substr($path, 0, -1);
        }

        $migrationFile = $path . "/" . self::fileName($class->name, $index);

        $migration = fopen($migrationFile, "w");

        self::writeUseStatements($migration);
        self::writeUp($migration, $class, $schema, $command);
        self::writeDown($migration, $class);

        fclose($migration);
    }
}