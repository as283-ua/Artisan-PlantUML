<?php

namespace As283\ArtisanPlantuml\Core;

use As283\ArtisanPlantuml\Util\SchemaUtil;
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
     * @param Schema &$schema
     * @param Command &$command
     * @param bool $usesTimeStamps
     * @return void
     */
    private static function writeUp($file, $class, &$schema, &$command, $usesTimeStamps = true)
    {
        fwrite($file, "        Schema::create('" . Pluralizer::plural(strtolower($class->name)) . "', function (Blueprint \$table) {\n");
        
        $classPKs = SchemaUtil::classKeys($class);
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

        // Write foreign keys
        foreach ($class->relatedClasses as $relatedClassName => $indexes) {
            if(count($indexes) == 1){
                $relation = $schema->relations[$indexes[0]];
    
                $cardinality = SchemaUtil::getCardinality($class->name, $relation);
    
                // Cardinality of many goes in another table, skip here
                if($cardinality == Cardinality::Any || $cardinality == Cardinality::AtLeastOne){
                    continue;
                }
    
                $otherCardinality = SchemaUtil::getCardinality($relatedClassName, $relation);
                if($otherCardinality == Cardinality::One || $otherCardinality == Cardinality::ZeroOrOne){
                    continue;
                }
    
                $otherClass = $schema->classes[$relatedClassName];
                $otherClassPKs = SchemaUtil::classKeys($otherClass);
                $otherUsesId = isset($otherClassPKs["id"]);
    
                if($otherUsesId){
                    fwrite($file, "            \$table->foreignId('" . strtolower($relatedClassName) . "_id')->constrained();\n");
                } else {
                    foreach ($otherClassPKs as $key => $type) {
                        fwrite($file, "            \$table->" . self::fieldTypeToLaravelType($type) . "('" . strtolower($relatedClassName) . "_" . $key . "');\n");
                        fwrite($file, "            \$table->foreign('" . strtolower($relatedClassName) . "_" . $key . "')->references('" . $key . "')->on('" . strtolower($relatedClassName) . "');\n");
                    }
                }
            } else {
                // may cause problems in the future for generating the model
                $j = 1;
                foreach($indexes as $index){
                    $relation = $schema->relations[$index];
    
                    $cardinality = SchemaUtil::getCardinality($class->name, $relation);
    
                    // Cardinality of many goes in another table, skip here
                    if($cardinality == Cardinality::Any || $cardinality == Cardinality::AtLeastOne){
                        continue;
                    }
    
                    $otherCardinality = SchemaUtil::getCardinality($relatedClassName, $relation);
                    if($otherCardinality == Cardinality::One || $otherCardinality == Cardinality::ZeroOrOne){
                        continue;
                    }
    
                    $otherClass = $schema->classes[$relatedClassName];
                    $otherClassPKs = SchemaUtil::classKeys($otherClass);
                    $otherUsesId = isset($otherClassPKs["id"]);
    
                    if($otherUsesId){
                        fwrite($file, "            \$table->foreignId('" . strtolower($relatedClassName) . "_id" . $j . "')->references('id')->on('" . strtolower($relatedClassName) . "');\n");
                    } else {
                        foreach ($otherClassPKs as $key => $type) {
                            fwrite($file, "            \$table->" . self::fieldTypeToLaravelType($type) . "('" . strtolower($relatedClassName) . "_" . $key . $j . "');\n");
                            fwrite($file, "            \$table->foreign('" . strtolower($relatedClassName) . "_" . $key . $j . "')->references('" . $key . "')->on('" . strtolower($relatedClassName) . "');\n");
                        }
                    }
                    $j++;
                }
            }
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
    public static function write($class, &$schema, $index, $command){
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

    /**
     * Write a migration file for the given class
     * @param string $class1
     * @param string $class2
     * @param Schema $schema
     * @param int $index
     * @param Command $command. For outputting messages to the console and getting command line parameters and options
     * @return void
     */
    public static function writeJunctionTable($class1, $class2, &$schema, $index, $command){
        // https://chat.openai.com/c/7f00fcad-0e08-4b5c-940b-e38ce8845ca8
    }
}