<?php
namespace As283\ArtisanPlantuml\Util;

use As283\ArtisanPlantuml\Exceptions\CycleException;
use As283\PlantUmlProcessor\Model\Schema;
use As283\PlantUmlProcessor\Model\Cardinality;

class SchemaUtil{
    /**
     * @param Schema &$schema
     * @return mixed
     */
    private static function initClassMap(&$schema){
        $classMap = [];
        foreach($schema->classes as $class){

            $classMap[$class->name] = [
                "resolved" => false,
                "relations" => []
            ];
            
            foreach ($class->relationIndexes as $index => $otherClassName) {
                $relation = $schema->relations[$index];
                /**
                 * @var Cardinality|null
                 */
                $cardinality = null;
                if($relation->from[0] === $class->name){
                    $cardinality = $relation->from[1];
                } else if($relation->to[0] === $class->name){
                    $cardinality = $relation->to[1];
                }
                
                $classMap[$class->name]["relations"][] = [
                    "class" => $otherClassName,
                    "cardinality" => $cardinality->toString(),
                    "resolved" => false
                ];
            }
        }
        
        return $classMap;
    }

    private static function printClassMap(&$classMap){
        echo json_encode($classMap, JSON_PRETTY_PRINT) . "\n";
    }


    /**
     * @param Schema &$schema
     * @return array[string]
     */
    public static function orderClasses(&$schema){
        $classMap = self::initClassMap($schema);

        /**
         * @var array[string]
         */
        $orderedClasses = [];

        $resolvedAll = false;

        $unorderedCount = count($classMap);
        $oldUnorderedCount = -1;

        while(!$resolvedAll){
            // failsafe
            if($oldUnorderedCount === $unorderedCount){
                throw new CycleException(array_keys($classMap));
            }

            // will run through all classes and do && of $resolved with $class->resolved
            $resolvedAll = true;
            foreach ($classMap as $classname => &$state) {

                // check if class is resolved or resolvable
                $resolvable = true;
                foreach($state["relations"] as &$relation){
                    if($relation["resolved"]){
                        continue;
                    }

                    $cardinality = Cardinality::fromString($relation["cardinality"]);
                    if($cardinality === Cardinality::Any || $cardinality === Cardinality::AtLeastOne){
                        $relation["resolved"] = true;
                        continue;
                    }

                    if($relation["class"] === $classname){
                        $relation["resolved"] = true;
                        continue;
                    }

                    // find out if other class also has 0..1 or 1 cardinality
                    $indexes = $schema->classes[$classname]->relationIndexes;
                    $index = -1;
                    foreach ($indexes as $i => $otherClass) {
                        if($otherClass === $relation["class"]){
                            $index = $i;
                            break;
                        }
                    }

                    $relationData = $schema->relations[$index];
                    $otherCardinality = null;
                    if($relationData->from[0] === $relation["class"]){
                        $otherCardinality = $relationData->from[1];
                    } else if($relationData->to[0] === $relation["class"]){
                        $otherCardinality = $relationData->to[1];
                    }

                    if($otherCardinality === Cardinality::One || $otherCardinality === Cardinality::ZeroOrOne){
                        $relation["resolved"] = true;
                        continue;
                    }
                    
                    // depends on other class, has fk to it
                    if(!array_key_exists($relation["class"], $classMap)){
                        $relation["resolved"] = true;
                        continue;
                    }

                    $resolvable = false;
                }

                $state["resolved"] = $resolvable;
                if($resolvable){
                    $orderedClasses[] = $classname;
                }

                $resolvedAll &= $state["resolved"];
            }

            // clean up $classMap by removing ordered ones
            foreach ($orderedClasses as $class) {
                if(array_key_exists($class, $classMap)){
                    unset($classMap[$class]);
                }
            }

            $oldUnorderedCount = $unorderedCount;
            $unorderedCount = count($classMap);
        }

        return $orderedClasses;
    }
}