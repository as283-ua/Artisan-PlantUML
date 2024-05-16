<?php

namespace As283\ArtisanPlantuml\Util;

use As283\ArtisanPlantuml\Exceptions\CycleException;
use As283\PlantUmlProcessor\Model\Schema;
use As283\PlantUmlProcessor\Model\Multiplicity;
use As283\PlantUmlProcessor\Model\ClassMetadata;
use As283\PlantUmlProcessor\Model\Relation;
use As283\PlantUmlProcessor\Model\Type;

class SchemaUtil
{
    /**
     * @param Schema &$schema
     * @return mixed
     */
    private static function initClassMap(&$schema)
    {
        /*
        [
            "class1" => [
                "resolved" => false,
                "relations" => [
                    [
                        "resolved" => false,
                        "class" => "class2",
                        "index" => 0,
                    ],
                    ...
                ]
            ],
            ...
        ]
        */
        $classMap = [];
        foreach ($schema->classes as $class) {

            $classMap[$class->name] = [
                "resolved" => false,
                "relations" => []
            ];

            foreach ($class->relatedClasses as $otherClassName => $relationIndexes) {
                foreach ($relationIndexes as $index) {
                    $classMap[$class->name]["relations"][] = [
                        "resolved" => false,
                        "class" => $otherClassName,
                        "index" => $index,
                    ];
                }
            }
        }

        return $classMap;
    }

    /**
     * @param Schema $schema
     * @return array Element at index 0 is an array<string> with the class names in order. Element at index 1 is an array<Relation> with the relations that were removed and their value.
     */
    public static function orderClasses($schema)
    {
        $classMap = self::initClassMap($schema);

        /**
         * @var array[string]
         */
        $orderedClasses = [];

        /**
         * @var array<Relation>
         */
        $conflictingRelations = [];

        $resolvedAll = false;

        $unorderedCount = count($classMap);
        $oldUnorderedCount = -1;

        while (!$resolvedAll) {
            // cycle detected
            if ($oldUnorderedCount === $unorderedCount) {
                foreach ($classMap as $class => $state) {
                    foreach ($state["relations"] as $relation) {
                        if (!$relation["resolved"]) {
                            if (($key = array_search($relation, $state["relations"])) !== false) {
                                $conflictingRelations[] = $schema->relations[$relation["index"]];
                                unset($schema->relations[$relation["index"]]);
                                unset($classMap[$class]["relations"][$key]);

                                // echo "Schema\n";
                                // print_r($schema);
                                // echo "\nClass map\n";
                                // print_r($classMap);
                                break;
                            }
                        }
                    }
                    break;
                }
            }

            // will run through all classes and do && of $resolved with $class->resolved
            $resolvedAll = true;
            foreach ($classMap as $classname => &$state) {

                // check if class is resolved or resolvable
                $resolvable = true;
                foreach ($state["relations"] as $i => &$relation) {
                    if ($relation["resolved"]) {
                        continue;
                    }

                    if (array_key_exists($relation["index"], $schema->relations) === false) {
                        unset($state["relations"][$i]);
                        continue;
                    }

                    $relationData = $schema->relations[$relation["index"]];

                    $multiplicity = self::getMultiplicity($classname, $relationData);
                    $otherMultiplicity = self::getMultiplicity($relation["class"], $relationData);

                    if ($otherMultiplicity === Multiplicity::Any || $otherMultiplicity === Multiplicity::AtLeastOne) {
                        $relation["resolved"] = true;
                        continue;
                    }

                    if ($relation["class"] === $classname) {
                        $relation["resolved"] = true;
                        continue;
                    }


                    if ($otherMultiplicity === Multiplicity::ZeroOrOne && $multiplicity === Multiplicity::One) {
                        $relation["resolved"] = true;
                        continue;
                    }

                    if ($otherMultiplicity === $multiplicity && $relation["class"] < $classname) {
                        $relation["resolved"] = true;
                        continue;
                    }

                    // depends on other class, has fk to it
                    if (!array_key_exists($relation["class"], $classMap)) {
                        $relation["resolved"] = true;
                        continue;
                    }

                    $resolvable = false;
                }

                $state["resolved"] = $resolvable;
                if ($resolvable) {
                    $orderedClasses[] = $classname;
                }

                $resolvedAll &= $state["resolved"];
            }

            // clean up $classMap by removing ordered ones
            foreach ($orderedClasses as $class) {
                if (array_key_exists($class, $classMap)) {
                    unset($classMap[$class]);
                }
            }

            $oldUnorderedCount = $unorderedCount;
            $unorderedCount = count($classMap);
        }

        return [$orderedClasses, $conflictingRelations];
    }

    /**
     * Get the multiplicity of a relation
     * @param string $className
     * @param Relation $relation
     * @return Multiplicity|null
     */
    public static function getMultiplicity($class, $relation)
    {
        if ($relation->from[0] === $class) {
            return $relation->from[1];
        } else if ($relation->to[0] === $class) {
            return $relation->to[1];
        }
        return null;
    }

    /**
     * Obtain list of fields that are part of the primary key with their data type. If one of the fields is "id", it is assumed to be the primary key and no further fields are considered. If no field is marked as primary, "id" is assumed to be the primary key. This function always return an array of at least one element.
     * @param ClassMetadata &$class
     * @param bool $allowsComposite
     * @return array<string,Type> List of fields that are part of the primary key . ["id"] if no primary key is defined
     */
    public static function classKeys(&$class, $allowsComposite = false)
    {
        $keys = [];
        foreach ($class->fields as $field) {
            if ($field->name === "id") {
                $field->primary = true;
                // might ignore type and just use id()
                return [$field->name => $field->type];
            } else if ($field->primary) {
                $keys[$field->name] = $field->type;
            }
        }

        if (count($keys) > 1 && !$allowsComposite) {
            $keys = ["id" => Type::int];
        }

        if (count($keys) == 0) {
            $keys = ["id" => Type::int];
        }

        return $keys;
    }



    /**
     * Convert a field type to a Laravel migration type
     * @param Field $fieldType
     * @return string|null
     */
    public static function fieldTypeToLaravelType($fieldType)
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
}
