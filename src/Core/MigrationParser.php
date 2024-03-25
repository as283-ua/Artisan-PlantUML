<?php

namespace As283\ArtisanPlantuml\Core;

use As283\PlantUmlProcessor\Model\Cardinality;
use As283\PlantUmlProcessor\Model\ClassMetadata;
use As283\PlantUmlProcessor\Model\Field;
use As283\PlantUmlProcessor\Model\Relation;
use As283\PlantUmlProcessor\Model\RelationType;
use As283\PlantUmlProcessor\Model\Schema;
use As283\PlantUmlProcessor\Model\Type;
use Illuminate\Support\Str;
use Parle\ErrorInfo;
use Parle\Parser;
use Parle\Lexer;
use Parle\ParserException;
use Parle\Token;

use const As283\ArtisanPlantuml\Util\NAMEDTYPES;

class MigrationParser
{
    private Parser $parser;
    private Lexer $lex;

    private int $DEFINITION;
    private int $TYPE;
    private int $TYPE_WITH_MODIFIER;
    private int $NAMELESS_TYPE;
    private int $MODIFIER;
    private int $MODIFIER_MANY;
    private int $TYPE_WITH_TWO_MODIFIER;
    private int $FOREIGN;
    private int $FOREIGN_ID;
    private int $FOREIGN_MANY;
    private int $FOREIGN_CUSTOM;
    private int $FOREIGN_CUSTOM_MANY;

    public function __construct()
    {
        /*
        * TOKENS
        */
        $this->parser = new Parser;
        $this->parser->nonassoc("SCHEMA_CREATE");
        $this->parser->nonassoc("DEF_LAMBDA");
        $this->parser->nonassoc("TYPE");
        $this->parser->nonassoc("NAMELESS_TYPE");
        $this->parser->token("MODIFIER");
        $this->parser->nonassoc("END");
        $this->parser->token("'->'");
        $this->parser->nonassoc("TABLE");
        $this->parser->token("TEXT");
        $this->parser->token("'('");
        $this->parser->token("')'");
        $this->parser->token("COMMA");
        $this->parser->token("'['");
        $this->parser->token("']'");
        $this->parser->token("'{'");
        $this->parser->token("'}'");
        $this->parser->nonassoc("FOREIGN");
        $this->parser->nonassoc("FOREIGN_ID");
        $this->parser->token("FOREIGN_LOCATION");

        /*
        * RULES
        */
        $this->parser->push("START", "DEFINITION");
        $this->parser->push("START", "LINE");

        $this->DEFINITION = $this->parser->push("DEFINITION", "SCHEMA_CREATE '(' TEXT COMMA DEF_LAMBDA '{' LINES '}' ')' END");
        $this->parser->push("LINES", "LINE");

        $this->parser->push("LINES", "LINES LINE");
        $this->parser->push("LINE", "TABLE '->' SPECS END");
        $this->parser->push("TEXTS", "TEXT");
        $this->parser->push("TEXTS", "TEXTS COMMA TEXT");
        $this->parser->push("TEXTS", "TEXTS COMMA");

        $this->TYPE_WITH_MODIFIER = $this->parser->push("SPECS", "TYPE '(' TEXT ')' '->' MODIFIER '(' ')'");
        $this->TYPE_WITH_TWO_MODIFIER = $this->parser->push("SPECS", "TYPE '(' TEXT ')' '->' MODIFIER '(' ')' '->' MODIFIER '(' ')'");
        $this->MODIFIER_MANY = $this->parser->push("SPECS", "MODIFIER '(' '[' TEXTS ']' ')'");
        $this->MODIFIER = $this->parser->push("SPECS", "MODIFIER '(' TEXT ')'");
        $this->TYPE = $this->parser->push("SPECS", "TYPE '(' TEXT ')'");
        $this->NAMELESS_TYPE = $this->parser->push("SPECS", "NAMELESS_TYPE '(' ')'");
        $this->FOREIGN_CUSTOM_MANY = $this->parser->push("SPECS", "FOREIGN '(' '[' TEXTS ']' ')' '->' FOREIGN_LOCATION '(' '[' TEXTS ']' ')' '->' FOREIGN_LOCATION '(' TEXT ')'");
        $this->FOREIGN_CUSTOM = $this->parser->push("SPECS", "FOREIGN '(' TEXT ')' '->' FOREIGN_LOCATION '(' TEXT ')' '->' FOREIGN_LOCATION '(' TEXT ')'");
        $this->FOREIGN_MANY = $this->parser->push("SPECS", "FOREIGN '(' '[' TEXTS ']' ')'");
        $this->FOREIGN = $this->parser->push("SPECS", "FOREIGN '(' TEXT ')'");
        $this->FOREIGN_ID = $this->parser->push("SPECS", "FOREIGN_ID '(' TEXT ')'");

        $this->parser->build();

        $this->lex = new Lexer;
        $this->lex->push("\$table", $this->parser->tokenId("TABLE"));
        $this->lex->push("->", $this->parser->tokenId("'->'"));
        $this->lex->push("(" . implode("|", NAMEDTYPES) . ")", $this->parser->tokenId("TYPE"));
        $this->lex->push("(id|rememberToken|timestamps)", $this->parser->tokenId("NAMELESS_TYPE"));
        $this->lex->push("(unique|nullable|primary)", $this->parser->tokenId("MODIFIER"));
        $this->lex->push(";", $this->parser->tokenId("END"));
        $this->lex->push("(\\\"\w*\\\"|'\w*')", $this->parser->tokenId("TEXT"));
        $this->lex->push("\\(", $this->parser->tokenId("'('"));
        $this->lex->push("\\)", $this->parser->tokenId("')'"));
        $this->lex->push("\\,", $this->parser->tokenId("COMMA"));
        $this->lex->push("\\[", $this->parser->tokenId("'['"));
        $this->lex->push("\\]", $this->parser->tokenId("']'"));
        $this->lex->push("\\{", $this->parser->tokenId("'{'"));
        $this->lex->push("\\}", $this->parser->tokenId("'}'"));
        $this->lex->push("foreign", $this->parser->tokenId("FOREIGN"));
        $this->lex->push("foreignId", $this->parser->tokenId("FOREIGN_ID"));
        $this->lex->push("(references|on)", $this->parser->tokenId("FOREIGN_LOCATION"));
        $this->lex->push("Schema::create", $this->parser->tokenId("SCHEMA_CREATE"));
        $this->lex->push("function\\s*\\(\\s*Blueprint\\s+\\\$table\\s*\\)", $this->parser->tokenId("DEF_LAMBDA"));
        $this->lex->push("\\s+", Token::SKIP);
        $this->lex->push("->constrained\\(\\)", Token::SKIP);
        $this->lex->push("->((cascadeOnDelete|nullOnDelete|noActionOnDelete|restrictOnDelete)\\(\\)|onDelete\\(\\\"\w+\\\"\\))", Token::SKIP);

        $this->lex->build();
    }

    /**
     * @param ErrorInfo $err
     * @param string $in
     */
    private static function throwParseError($err, $in, $marker)
    {
        if (Parser::ERROR_UNKNOWN_TOKEN == $err->id) {
            $tok = $err->token;
            $msg = "Unknown token '{$tok->value}' in '{$in}' at offset {$err->position}";
        } else if (Parser::ERROR_NON_ASSOCIATIVE == $err->id) {
            $tok = $err->token;
            $msg = "Token '{$tok->id}' in '{$in}' at offset {$marker} is not associative";
        } else if (Parser::ERROR_SYNTAX == $err->id) {
            $tok = $err->token;
            $msg = "Syntax error in '{$in}' at offset {$marker} '{$tok->value}'";
        } else {
            $msg = "Parse error";
        }
        throw new ParserException($msg);
    }

    /**
     * @param ClassMetadata &$class
     * @param Schema &$schema
     * @return void
     */
    private function handleDefinition(&$schema, &$class)
    {
        // name capitalized and singular
        $classname = ucfirst(Str::singular(self::removeQuotes($this->parser->sigil(2))));

        if (array_key_exists($classname, $schema->classes)) {
            $class = $schema->classes[$classname];
        } else {
            $class->name = $classname;
        }
    }

    /**
     * @param ClassMetadata &$class
     * @return void
     */
    private function handleType(&$class)
    {
        $type = $this->parser->sigil(0);
        $fieldname = self::removeQuotes($this->parser->sigil(2));
        $field = new Field();
        $field->name = $fieldname;
        $field->type = Type::fromString($type);
        $class->fields[] = $field;
    }

    private function handleNameless(&$class)
    {
        if ($this->parser->sigil(0) === "id") {
            $field = new Field();
            $field->name = "id";
            $field->type = Type::int;
            $field->primary = true;
            $class->fields[] = $field;
        }
    }

    /**
     * @param ClassMetadata &$class
     * @return void
     */
    private function handleTypeWithModifier(&$class)
    {
        $type = $this->parser->sigil(0);
        $fieldname = self::removeQuotes($this->parser->sigil(2));
        $modifier = $this->parser->sigil(5);

        $field = new Field();
        $field->name = $fieldname;
        $field->type = Type::fromString($type);
        switch ($modifier) {
            case "unique":
                $field->unique = true;
                break;
            case "nullable":
                $field->nullable = true;
                break;
            case "primary":
                $field->primary = true;
                break;
        }
        $class->fields[] = $field;
    }

    /**
     * @param ClassMetadata &$class
     * @return void
     */
    private function handleTypeWithTwoModifiers(&$class)
    {
        $type = $this->parser->sigil(0);
        $fieldname = self::removeQuotes($this->parser->sigil(2));
        $modifiers = [$this->parser->sigil(5), $this->parser->sigil(9)];

        $field = new Field();
        $field->name = $fieldname;
        $field->type = Type::fromString($type);
        foreach ($modifiers as $modifier) {
            switch ($modifier) {
                case "unique":
                    $field->unique = true;
                    break;
                case "nullable":
                    $field->nullable = true;
                    break;
                case "primary":
                    $field->primary = true;
                    break;
            }
        }
        $class->fields[] = $field;
    }

    /**
     * @param ClassMetadata &$class
     * @return void
     */
    private function handleModifier(&$class)
    {

        $modifier = $this->parser->sigil(0);
        $fieldname = self::removeQuotes($this->parser->sigil(2));

        $i = 0;
        for ($i = 0; $i < count($class->fields); $i++) {
            if ($class->fields[$i]->name === $fieldname) {
                return;
            }
        }

        if ($i == count($class->fields)) {
            return;
        }

        switch ($modifier) {
            case "unique":
                $class->fields[$i]->unique = true;
                break;
            case "nullable":
                $class->fields[$i]->nullable = true;
                break;
            case "primary":
                $class->fields[$i]->primary = true;
                break;
        }
    }

    /**
     * @param ClassMetadata &$class
     * @return void
     */
    private function handleManyModifiers(&$class)
    {
        $modifier = $this->parser->sigil(0);
        $fieldnames = array_map(
            function ($x) {
                return trim($x);
            },
            explode(",", self::removeQuotes($this->parser->sigil(3)))
        );

        $found = 0;
        $nFields = count($fieldnames);
        for ($i = 0; $i < count($class->fields); $i++) {
            if ($found >= $nFields) {
                break;
            }

            if (in_array($class->fields[$i]->name, $fieldnames)) {
                switch ($modifier) {
                    case "unique":
                        $class->fields[$i]->unique = true;
                        break;
                    case "nullable":
                        $class->fields[$i]->nullable = true;
                        break;
                    case "primary":
                        $class->fields[$i]->primary = true;
                        break;
                }
                $found++;
                break;
            }
        }
    }

    /**
     * @param Schema &$schema
     * @param ClassMetadata &$class
     * @param int[] &$relationIndexes
     * @return void
     */
    private function handleForeign(&$schema, &$class, &$relationIndexes)
    {
        $fieldname = self::removeQuotes($this->parser->sigil(2));

        // remove this field
        $i = 0;
        for (; $i < count($class->fields); $i++) {
            if ($class->fields[$i]->name === $fieldname) {
                break;
            }
        }

        if ($i == count($class->fields)) {
            return;
        }

        $fieldCopy = $class->fields[$i];

        array_splice($class->fields, $i, 1);

        $class_pk = explode("_", $fieldname);
        if (count($class_pk) < 1) {
            return;
        }

        $otherclassname = Str::singular(ucfirst($class_pk[0]));

        $cardinality = Cardinality::One;
        if ($fieldCopy->nullable) {
            $cardinality = Cardinality::ZeroOrOne;
        }

        $otherCardinality = Cardinality::Any;
        if ($fieldCopy->unique) {
            $otherCardinality = Cardinality::ZeroOrOne;
        }

        $relation = new Relation();
        $relation->from = ["", $cardinality];
        // we can't know if the other must have at least one, that is defined in the program logic, not db specification
        $relation->to = [$otherclassname, $otherCardinality];
        $relation->type = RelationType::Association;

        $schema->relations[] = $relation;
        $relationIndexes[] = count($schema->relations) - 1;
    }

    /**
     * @param Schema &$schema
     * @param int[] &$relationIndexes
     * @return void
     */
    private function handleForeignId(&$schema, &$relationIndexes)
    {
        $fieldname = self::removeQuotes($this->parser->sigil(2));

        $class_pk = explode("_", $fieldname);
        if (count($class_pk) < 1) {
            return;
        }

        $otherclassname = Str::singular(ucfirst($class_pk[0]));

        $relation = new Relation();
        $relation->from = ["", Cardinality::ZeroOrOne];
        // we can't know if the other must have at least one, that is defined in the program logic, not db specification
        $relation->to = [$otherclassname, Cardinality::Any];
        $relation->type = RelationType::Association;

        $schema->relations[] = $relation;
        $relationIndexes[] = count($schema->relations) - 1;
    }

    /**
     * @param Schema &$schema
     * @param ClassMetadata &$class
     * @param int[] &$relationIndexes
     * @return void
     */
    private function handleForeignMany(&$schema, &$class, &$relationIndexes)
    {
        $fieldnames = array_map(function ($x) {
            return trim($x);
        }, explode(",", self::removeQuotes($this->parser->sigil(3))));

        $found = 0;
        $fieldCopy = null;
        // remove FK fields
        for ($i = 0; $i < count($class->fields); $i++) {
            if ($found >= count($fieldnames)) {
                break;
            }

            if (in_array($class->fields[$i]->name, $fieldnames)) {
                $fieldCopy = $class->fields[$i];
                array_splice($class->fields, $i, 1);
                $found++;
                // avoid problems because of the splice and i++
                $i--;
            }
        }

        $class_pk = explode("_", $fieldnames[0]);
        if (count($class_pk) < 1) {
            return;
        }

        $otherclassname = Str::singular(ucfirst($class_pk[0]));

        $cardinality = Cardinality::One;
        if ($fieldCopy->nullable) {
            $cardinality = Cardinality::ZeroOrOne;
        }

        $otherCardinality = Cardinality::Any;
        if ($fieldCopy->unique) {
            $otherCardinality = Cardinality::ZeroOrOne;
        }

        $relation = new Relation();
        $relation->from = ["", $cardinality];
        $relation->to = [$otherclassname, $otherCardinality];
        $relation->type = RelationType::Association;

        $schema->relations[] = $relation;
        $relationIndexes[] = count($schema->relations) - 1;
    }

    /**
     * @param Schema &$schema
     * @param ClassMetadata &$class
     * @param int[] &$relationIndexes
     * @return void
     */
    private function handleForeignCustom(&$schema, &$class, &$relationIndexes)
    {
        $fieldname = self::removeQuotes($this->parser->sigil(2));

        //remove field
        $i = 0;
        for (; $i < count($class->fields); $i++) {
            if ($class->fields[$i]->name === $fieldname) {
                break;
            }
        }

        if ($i == count($class->fields)) {
            return;
        }

        $fieldCopy = $class->fields[$i];
        array_splice($class->fields, $i, 1);


        $otherclass = "";
        if ($this->parser->sigil(5) === "on") {
            $otherclass = Str::singular(ucfirst(self::removeQuotes($this->parser->sigil(7))));
        } else if ($this->parser->sigil(10) === "on") {
            $otherclass = Str::singular(ucfirst(self::removeQuotes($this->parser->sigil(12))));
        } else {
            return;
        }

        $cardinality = Cardinality::One;
        if ($fieldCopy->nullable) {
            $cardinality = Cardinality::ZeroOrOne;
        }

        $otherCardinality = Cardinality::Any;
        if ($fieldCopy->unique) {
            $otherCardinality = Cardinality::ZeroOrOne;
        }

        $relation = new Relation();
        $relation->from = ["", $cardinality];
        $relation->to = [$otherclass, $otherCardinality];
        $relation->type = RelationType::Association;

        $schema->relations[] = $relation;
        $relationIndexes[] = count($schema->relations) - 1;
    }

    /**
     * @param Schema &$schema
     * @param ClassMetadata &$class
     * @param int[] &$relationIndexes
     * @return void
     */
    private function handleManyForeignCustom(&$schema, &$class, &$relationIndexes)
    {
        $fieldnames = array_map(function ($x) {
            return trim($x);
        }, explode(",", self::removeQuotes($this->parser->sigil(3))));

        $found = 0;
        $fieldCopy = null;
        for ($i = 0; $i < count($class->fields); $i++) {
            if ($found >= count($fieldnames)) {
                break;
            }

            if (in_array($class->fields[$i]->name, $fieldnames)) {
                $fieldCopy = $class->fields[$i];
                array_splice($class->fields, $i, 1);
                $found++;
                // avoid problems because of the splice and i++
                $i--;
            }
        }

        $otherclass = "";
        if ($this->parser->sigil(14) === "on") {
            $otherclass = Str::singular(ucfirst(self::removeQuotes($this->parser->sigil(16))));
        } else {
            return;
        }

        $cardinality = Cardinality::One;
        if ($fieldCopy->nullable) {
            $cardinality = Cardinality::ZeroOrOne;
        }

        $otherCardinality = Cardinality::Any;
        if ($fieldCopy->unique) {
            $otherCardinality = Cardinality::ZeroOrOne;
        }

        $relation = new Relation();
        $relation->from = ["", $cardinality];
        $relation->to = [$otherclass, $otherCardinality];
        $relation->type = RelationType::Association;

        $schema->relations[] = $relation;
        $relationIndexes[] = count($schema->relations) - 1;
    }

    /**
     * @param string $in
     * @param Schema $schema
     */
    public function parse($in, &$schema)
    {
        $this->parser->consume($in, $this->lex);
        $class = new ClassMetadata();

        // our class name is not known until the end, must change relations->from[0] to name of out class
        $relationIndexes = [];
        do {
            switch ($this->parser->action) {
                case Parser::ACTION_ERROR:
                    self::throwParseError($this->parser->errorInfo(), $in, $this->lex->marker);
                    break;
                case Parser::ACTION_SHIFT:
                case Parser::ACTION_GOTO:
                case Parser::ACTION_ACCEPT:
                    break;
                case Parser::ACTION_REDUCE:
                    switch ($this->parser->reduceId) {
                        case $this->DEFINITION:
                            echo "DEFINITION\n";
                            $this->handleDefinition($schema, $class);
                            break;
                        case $this->TYPE:
                            echo "TYPE\n";
                            $this->handleType($class);
                            break;
                        case $this->NAMELESS_TYPE:
                            echo "NAMELESS_TYPE\n";
                            $this->handleNameless($class);
                            break;
                        case $this->TYPE_WITH_MODIFIER:
                            echo "TYPE_WITH_MODIFIER\n";
                            $this->handleTypeWithModifier($class);
                            break;
                        case $this->TYPE_WITH_TWO_MODIFIER:
                            echo "TYPE_WITH_TWO_MODIFIER\n";
                            $this->handleTypeWithTwoModifiers($class);
                            break;
                        case $this->MODIFIER:
                            echo "MODIFIER\n";
                            $this->handleModifier($class);
                            break;
                        case $this->MODIFIER_MANY:
                            echo "MODIFIER_MANY\n";
                            $this->handleManyModifiers($class);
                            break;
                        case $this->FOREIGN:
                            echo "FOREIGN\n";
                            $this->handleForeign($schema, $class, $relationIndexes);
                            break;
                        case $this->FOREIGN_ID:
                            echo "FOREIGN_ID\n";
                            $this->handleForeignId($schema, $relationIndexes);
                            break;
                        case $this->FOREIGN_MANY:
                            echo "FOREIGN_MANY\n";
                            $this->handleForeignMany($schema, $class, $relationIndexes);
                            break;
                        case $this->FOREIGN_CUSTOM:
                            echo "FOREIGN_CUSTOM\n";
                            $this->handleForeignCustom($schema, $class, $relationIndexes);
                            break;
                        case $this->FOREIGN_CUSTOM_MANY:
                            echo "FOREIGN_CUSTOM_MANY\n";
                            $this->handleManyForeignCustom($schema, $class, $relationIndexes);
                            break;
                    }
                    break;
            }
            $this->parser->advance();
        } while (Parser::ACTION_ACCEPT != $this->parser->action);

        if ((count($class->fields) == 1) && ($class->fields[0]->name === "id")) {
            $classes = array_map(fn ($x) => ucfirst($x), explode("_", $class->name));
            $relation = new Relation();
            $relation->from = [$classes[0], Cardinality::Any];
            $relation->to = [$classes[1], Cardinality::Any];
            $relation->type = RelationType::Association;

            $schema->relations[] = $relation;
            for ($i = count($relationIndexes) - 1; $i >= 0; $i--) {
                array_splice($schema->relations, $relationIndexes[$i], 1);
            }
        } else {
            $schema->classes[$class->name] = $class;
            foreach ($relationIndexes as $i) {
                $schema->relations[$i]->from[0] = $class->name;
            }
        }
    }

    private static function removeQuotes($str)
    {
        return str_replace(["\"", "'"], "", $str);
    }
}
