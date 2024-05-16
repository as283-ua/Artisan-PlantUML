<?php

namespace As283\ArtisanPlantuml\Core;

use As283\ArtisanPlantuml\Util\SchemaUtil;
use Illuminate\Support\Pluralizer;
use As283\PlantUmlProcessor\Model\ClassMetadata;
use As283\PlantUmlProcessor\Model\Schema;
use As283\PlantUmlProcessor\Model\Type;
use As283\PlantUmlProcessor\Model\Multiplicity;
use As283\PlantUmlProcessor\Model\Relation;
use Illuminate\Console\Command;

class MigrationWriter
{
    /**
     * Generate a file name for the migration
     * @param string|array $className
     * @param int|null $index
     * @return string
     */
    private static function fileName($className, $index = null)
    {
        $table = "";
        if (is_array($className)) {
            $table = self::junctionTableName($className);
        } else {
            $table = Pluralizer::plural(strtolower($className));
        }

        $indexStr = $index === null ? "" : "_" . $index;
        return date("Y_m_d_His") . $indexStr . "_create_" . $table . "_table.php";
    }

    /**
     * @param string $tableName
     * @param Relation $relation
     * @param int $index
     */
    private static function fileNameFKs($tableName, $relatedTable, $index)
    {
        return date("Y_m_d_His") . "_" . $index . "_add_" . $relatedTable . "_foreign_key_to_" . $tableName . "_table.php";
    }

    /**
     * Generate a junction table name for the migration
     * @param array $classes
     * @return string
     */
    private static function junctionTableName($classes)
    {
        sort($classes);
        foreach ($classes as &$name) {
            $name = strtolower($name);
        }

        return implode("_", $classes);
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
     * Write the fields for the migration
     * @param resource $file
     * @param ClassMetadata $class
     * @param Command &$command
     */
    private static function writeFields($file, &$class, &$command)
    {
        foreach ($class->fields as $field) {
            if ($field->name == "id") {
                continue;
            }

            if ($field->type == null) {
                $command->warn("Field " . $field->name . " has no type. Skipping.");
                continue;
            }

            $type = SchemaUtil::fieldTypeToLaravelType($field->type);
            if ($type == null) {
                $command->warn("Field " . $field->name . " has an unsupported type. Skipping.");
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

        $useComposite = $command->option("use-composite-keys");
        if (!$useComposite) {
            $keys = SchemaUtil::classKeys($class, true);
            if (count($keys) > 1) {
                $uniques = implode("', '", array_keys($keys));
                fwrite($file, "            \$table->unique(['{$uniques}']);\n");
            }
        }
    }

    /**
     * Write the primary keys for the migration
     * @param resource $file
     * @param array<string,Type> $classPKs
     * @param bool $usesId
     */
    private static function writePKs($file, $classPKs)
    {
        $usesId = isset($classPKs["id"]);
        if ($usesId) {
            fwrite($file, "            \$table->id();\n");
            return;
        }

        if (count($classPKs) > 1) {
            fwrite($file, "            \$table->primary([");
            foreach ($classPKs as $pk => $ignore) {
                fwrite($file, "'" . $pk . "', ");
            }
            fwrite($file, "]);\n");
        } else if (count($classPKs) == 1 && !$usesId) {
            $classKeys = array_keys($classPKs);

            fwrite($file, "            \$table->primary('" . $classKeys[0] . "');\n");
        }
    }

    /**
     * Write the foreign keys for the migration
     * @param resource $file
     * @param ClassMetadata &$class
     * @param Schema &$schema
     * @param Command &$command
     */
    private static function writeFKs($file, &$class, &$schema, &$command)
    {
        foreach ($class->relatedClasses as $relatedClassName => $indexes) {
            $tableName = Pluralizer::plural(strtolower($relatedClassName));
            $j = 1;
            if (count($indexes) == 1) {
                $j = null;
            }

            foreach ($indexes as $index) {
                if (array_key_exists($index, $schema->relations) === false) {
                    continue;
                }
                $relation = $schema->relations[$index];

                $otherMultiplicity = SchemaUtil::getMultiplicity($class->name, $relation);
                $multiplicity = SchemaUtil::getMultiplicity($relatedClassName, $relation);

                // when a class is related to itself, choose the most restrictive multiplicity for this iteration so that it doesn't skip the relation
                if ($class->name === $relatedClassName) {
                    $multiplicity = $relation->from[1];
                    $otherMultiplicity = $relation->to[1];

                    if ((($multiplicity === Multiplicity::Any || $multiplicity === Multiplicity::AtLeastOne) &&
                            ($otherMultiplicity === Multiplicity::One || $otherMultiplicity === Multiplicity::ZeroOrOne)) ||

                        ($multiplicity === Multiplicity::ZeroOrOne  && $otherMultiplicity === Multiplicity::One)
                    ) {
                        $aux = $multiplicity;
                        $multiplicity = $otherMultiplicity;
                        $otherMultiplicity = $aux;
                    }
                }

                // Multiplicity of many goes in another table, skip here
                if ($multiplicity == Multiplicity::Any || $multiplicity == Multiplicity::AtLeastOne) {
                    continue;
                }

                if ($class->name !== $relatedClassName) {
                    // Fk goes in more restrictive table
                    if ($multiplicity == Multiplicity::ZeroOrOne && $otherMultiplicity == Multiplicity::One) {
                        continue;
                    }

                    // Fk goes in table who is alphabetically first
                    if ($multiplicity === $otherMultiplicity && $relatedClassName < $class->name) {
                        continue;
                    }
                } else {
                    // set relation to the most restrictive one
                    if ($otherMultiplicity == Multiplicity::One) {
                        $otherMultiplicity = $multiplicity;
                        $multiplicity = Multiplicity::One;
                    }
                }

                $otherClass = $schema->classes[$relatedClassName];
                $otherClassPKs = SchemaUtil::classKeys($otherClass, $command->option("use-composite-keys"));

                $usesId = isset($otherClassPKs["id"]);
                $useForeignId = $usesId && count($indexes) <= 1;

                if ($usesId && !$useForeignId) {
                    $otherClassPKs["id"] = Type::bigint;
                }

                $unique = in_array($otherMultiplicity, [Multiplicity::One, Multiplicity::ZeroOrOne]);
                $nullable = $multiplicity == Multiplicity::ZeroOrOne;

                if ($useForeignId) {
                    $unique = $unique ? "->unique()" : "";
                    $nullable = $nullable ? "->nullable()" : "";
                    fwrite($file, "            \$table->foreignId('" . strtolower($relatedClassName) . "_id')" . $unique . $nullable . "->constrained();\n");
                } else {
                    $fks = [];
                    foreach ($otherClassPKs as $key => $type) {
                        $columnName = strtolower($relatedClassName) . "_" . $key . $j;
                        $nullable = $nullable ? "->nullable()" : "";

                        $laravelType = SchemaUtil::fieldTypeToLaravelType($type);
                        if ($key === "id") {
                            $laravelType = "unsignedBigInteger";
                        }

                        fwrite($file, "            \$table->" . $laravelType . "('" . $columnName . "')" . $nullable . ";\n");
                        $fks[] = $columnName;
                    }

                    if ($unique) {
                        $unique = $unique ? "->unique(['" . implode("', '", $fks) . "'])" : "";
                        fwrite($file, "            \$table" . $unique . ";\n");
                    }
                    fwrite($file, "            \$table->foreign(['" . implode("', '", $fks) . "'])->references(['" . implode("', '", array_keys($otherClassPKs)) . "'])->on('" . $tableName . "');\n");
                }

                if (!isset($j)) {
                    break;
                }

                // don't write the same fk twice
                // if ($class->name === $relatedClassName) {
                //     break;
                // }

                $j++;
            }
        }
    }

    /**
     * Write the foreign keys for a specific class in a junction table
     * @param resource $file
     * @param string $class
     * @param int $relationIndex
     * @param Schema &$schema
     * @param Command &$command
     */
    private static function writeFKsJunction($file, $class, &$schema, &$command)
    {
        $tableName = Pluralizer::plural(strtolower($class));

        $otherClass = $schema->classes[$class];
        $otherClassPKs = SchemaUtil::classKeys($otherClass, $command->option("use-composite-keys"));
        $otherUsesId = isset($otherClassPKs["id"]);

        if ($otherUsesId) {
            fwrite($file, "            \$table->foreignId('" . strtolower($class) . "_id')->constrained();\n");
        } else {
            $fks = [];
            foreach ($otherClassPKs as $key => $type) {
                $columnName = strtolower($class) . "_" . $key;
                fwrite($file, "            \$table->" . SchemaUtil::fieldTypeToLaravelType($type) . "('" . $columnName . "');\n");
                $fks[] = $columnName;
            }
            fwrite($file, "            \$table->foreign(['" . implode("', '", $fks) . "'])->references(['" . implode("', '", array_keys($otherClassPKs)) . "'])->on('" . $tableName . "');\n");
        }
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

        $classPKs = SchemaUtil::classKeys($class, $command->option("use-composite-keys"));

        self::writePKs($file, $classPKs);

        self::writeFields($file, $class, $command);

        self::writeFKs($file, $class, $schema, $command);

        if ($usesTimeStamps) {
            fwrite($file, "            \$table->timestamps();\n");
        }

        fwrite($file, "        });\n");
        fwrite($file, "    }\n\n");
    }

    /**
     * @param resource $file
     * @param Relation $relation
     * @param array<string,Type> $otherClassKeys
     */
    private static function writeUpAddFks($file, $relation, $otherClassKeys)
    {
        $otherMultiplicity = $relation->from[1];
        $multiplicity = $relation->to[1];

        $table = Pluralizer::plural(strtolower($relation->from[0]));
        $otherTable = Pluralizer::plural(strtolower($relation->to[0]));

        if (Multiplicity::compare($multiplicity, $otherMultiplicity) === -1) {
            $aux = $multiplicity;
            $multiplicity = $otherMultiplicity;
            $otherMultiplicity = $aux;

            $table = strtolower($relation->to[0]);
            $otherTable = strtolower($relation->from[0]);
        }

        // $multiplicity is the more restrictive one

        fwrite($file, "        Schema::table('" . $table . "', function (Blueprint \$table) {\n");

        $unique = in_array($otherMultiplicity, [Multiplicity::One, Multiplicity::ZeroOrOne]);
        $nullable = $multiplicity == Multiplicity::ZeroOrOne;

        $otherUsesId = isset($otherClassPKs["id"]);

        if ($otherUsesId) {
            $unique = $unique ? "->unique()" : "";
            $nullable = $nullable ? "->nullable()" : "";
            fwrite($file, "            \$table->foreignId('" . $otherTable . "_id')" . $unique . $nullable . "->constrained();\n");
        } else {
            $fks = [];
            foreach ($otherClassKeys as $key => $type) {
                $columnName = $otherTable . "_" . $key;
                $nullable = $nullable ? "->nullable()" : "";
                fwrite($file, "            \$table->" . SchemaUtil::fieldTypeToLaravelType($type) . "('" . $columnName . "')" . $nullable . ";\n");
                $fks[] = $columnName;
            }

            if ($unique) {
                fwrite($file, "            \$table->unique(['" . implode("', '", $fks) . "']);\n");
            }

            fwrite($file, "            \$table->foreign(['" . implode("', '", $fks) . "'])->references(['" . implode("', '", array_keys($otherClassKeys)) . "'])->on('" . $otherTable . "');\n");
        }

        fwrite($file, "        });\n    }\n\n");
    }

    /**
     * Generate the down method for the migration
     * @param string $tableName
     * @param resource $file
     * @return void
     */
    private static function writeDown($file, $tableName)
    {
        fwrite($file, "    public function down(): void\n");
        fwrite($file, "    {\n");
        fwrite($file, "        Schema::dropIfExists('" . $tableName . "');\n");
        fwrite($file, "    }\n");
        fwrite($file, "};");
    }

    /**
     * @param resource $file
     * @param string $tableName
     * @param string|string[] $columns
     */
    private static function writeDownFkColumn($file, $tableName, $otherTable, $columns)
    {
        if (is_array($columns) && count($columns) == 1) {
            $columns = $columns[0];
        }

        fwrite($file, "    public function down(): void\n");
        fwrite($file, "    {\n");
        fwrite($file, "        Schema::table('" . $tableName . "', function (Blueprint \$table) {\n");
        if (is_array($columns)) {
            fwrite($file, "            \$table->dropForeign(['{$otherTable}_" . implode("', '{$otherTable}_", $columns) . "']);\n");
            foreach ($columns as $columnName) {
                fwrite($file, "            \$table->dropColumn('{$otherTable}_" . $columnName . "');\n");
            }
        } else {
            fwrite($file, "            \$table->dropForeign(['{$otherTable}_" . $columns . "']);\n");
            fwrite($file, "            \$table->dropColumn('{$otherTable}_" . $columns . "');\n");
        }
        fwrite($file, "        });\n");
        fwrite($file, "    }\n");
        fwrite($file, "};");
    }

    /**
     * @param resource $file
     * @param ClassMetadata $class1
     * @param ClassMetadata $class2
     * @param Schema &$schema
     * @param Command &$command 
     */
    private static function writeJunctionUp($file, $class1, $class2, &$schema, &$command)
    {
        fwrite($file, "        Schema::create('" . self::junctionTableName([$class1->name, $class2->name]) . "', function (Blueprint \$table) {\n");

        fwrite($file, "            \$table->id();\n");

        self::writeFKsJunction($file, $class1->name, $schema, $command);
        self::writeFKsJunction($file, $class2->name, $schema, $command);

        fwrite($file, "        });\n");
        fwrite($file, "    }\n\n");
    }


    /**
     * Write a migration file for the given class
     * @param string $class
     * @param Schema $schema
     * @param int $index
     * @param Command $command. For outputting messages to the console and getting command line parameters and options
     * @return void
     */
    public static function write($className, &$schema, $index, $command)
    {
        $path = $command->option("path-migrations");

        // Remove trailing slash
        if ($path[-1] == "/") {
            $path = substr($path, 0, -1);
        }
        $class = $schema->classes[$className];
        $migrationFile = $path . "/" . self::fileName($class->name, $index);

        $migration = fopen($migrationFile, "w");

        self::writeUseStatements($migration);
        self::writeUp($migration, $class, $schema, $command);
        self::writeDown($migration, Pluralizer::plural(strtolower($class->name)));

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
    public static function writeJunctionTable($class1, $class2, &$schema, $index, $command)
    {
        $path = $command->option("path-migrations"); // Remove trailing slash

        if ($path[-1] == "/") {
            $path = substr($path, 0, -1);
        }

        $migrationFile = $path . "/" . self::fileName([$class1, $class2], $index);

        $migration = fopen($migrationFile, "w");

        $class1Data = $schema->classes[$class1];
        $class2Data = $schema->classes[$class2];

        self::writeUseStatements($migration);
        self::writeJunctionUp($migration, $class1Data, $class2Data, $schema, $command);
        self::writeDown($migration, self::junctionTableName([$class1, $class2]));

        fclose($migration);
    }

    /**
     * @param Relation $relation
     * @param Schema $schema
     * @param int $index
     * @param Command $command
     */
    public static function writeMissingRelation($relation, $schema, $index, $command)
    {
        if ($relation->from[1] === Multiplicity::One || $relation->from[1] === Multiplicity::ZeroOrOne) {
            $className = $relation->to[0];
            $otherClassName = $relation->from[0];
        } else {
            $className = $relation->from[0];
            $otherClassName = $relation->to[0];
        }

        $tableName = Pluralizer::plural(strtolower($className));
        $relatedTable = Pluralizer::plural(strtolower($otherClassName));

        $path = $command->option("path-migrations");

        if ($path[-1] == "/") {
            $path = substr($path, 0, -1);
        }

        $migrationFile = $path . "/" . self::fileNameFKs($tableName, $relatedTable, $index);

        $migration = fopen($migrationFile, "w");

        $otherClassKeys = SchemaUtil::classKeys($schema->classes[$otherClassName], $command->option("use-composite-keys"));

        self::writeUseStatements($migration);
        self::writeUpAddFks($migration, $relation, $otherClassKeys);

        $fieldNames = array_keys($otherClassKeys);

        self::writeDownFkColumn($migration, $tableName, $relatedTable, $fieldNames);
    }
}
