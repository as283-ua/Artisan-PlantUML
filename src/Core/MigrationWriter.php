<?php

namespace As283\ArtisanPlantuml\Core;

use Illuminate\Support\Pluralizer;
use As283\PlantUmlProcessor\Model\ClassMetadata;
use As283\PlantUmlProcessor\Model\Field;
use As283\PlantUmlProcessor\Model\Type;
use Illuminate\Console\Command;

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
     * @param ClassMetadata $class
     * @param resource $file
     * @param Command $command
     * @return void
     */
    private static function writeUp($file, $class, $command)
    {
        fwrite($file, "        Schema::create('" . Pluralizer::plural(strtolower($class->name)) . "', function (Blueprint \$table) {\n");
        
        $usesId = true;
        if($usesId){
            fwrite($file, "            \$table->id();\n");
        }

        $usesTimeStamps = true;
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
            if ($field->nullable) {
                fwrite($file, "->nullable()");
            }
            if ($field->unique) {
                fwrite($file, "->unique()");
            }
            fwrite($file, ";\n");
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
     * @param Command $command
     * @param string $path
     * @return void
     */
    public static function write($class, $command, $path = "database/migrations")
    {
        // Remove trailing slash
        if($path[-1] == "/"){
            $path = substr($path, 0, -1);
        }

        $migrationFile = $path . "/" . self::fileName($class->name);

        $migration = fopen($migrationFile, "w");

        self::writeUseStatements($migration);
        self::writeUp($migration, $class, $command);
        self::writeDown($migration, $class);

        fclose($migration);
    }
}