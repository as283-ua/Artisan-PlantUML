<?php

namespace ArtisanPlantUML\Core;

use As283\PlantUmlProcessor\Model\Schema;
use Illuminate\Support\Str;
use Parle\ErrorInfo;
use Parle\Parser;
use Parle\Lexer;
use Parle\ParserException;
use Parle\Token;

class MigrationParser
{
    private Parser $parser;
    private Lexer $lex;

    private int $TYPE;
    private int $TYPE_WITH_MODIFIER;
    private int $NAMELESS_TYPE;
    private int $MODIFIER;
    private int $MODIFIER_MANY;
    private int $TYPE_WITH_TWO_MODIFIER;
    private int $FOREIGN;
    private int $FOREIGN_MANY;
    private int $FOREIGN_CUSTOM;
    private int $FOREIGN_CUSTOM_MANY;

    public function __construct()
    {
        /*
        * TOKENS
        */
        $this->parser = new Parser;
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
        $this->parser->nonassoc("FOREIGN");
        $this->parser->token("FOREIGN_LOCATION");

        $this->parser->push("START", "LINE");
        $this->parser->push("LINE", "TABLE '->' DEFINITION END");
        $this->parser->push("TEXTS", "TEXT");
        $this->parser->push("TEXTS", "TEXTS COMMA TEXT");
        $this->parser->push("TEXTS", "TEXTS COMMA");

        /*
        * RULES
        */
        $this->TYPE_WITH_MODIFIER = $this->parser->push("DEFINITION", "TYPE '(' TEXT ')' '->' MODIFIER '(' ')'");
        $this->TYPE_WITH_TWO_MODIFIER = $this->parser->push("DEFINITION", "TYPE '(' TEXT ')' '->' MODIFIER '(' ')' '->' MODIFIER '(' ')'");
        $this->TYPE = $this->parser->push("DEFINITION", "TYPE '(' TEXT ')'");
        $this->NAMELESS_TYPE = $this->parser->push("DEFINITION", "NAMELESS_TYPE '(' ')'");
        $this->FOREIGN_CUSTOM_MANY = $this->parser->push("DEFINITION", "FOREIGN '(' '[' TEXTS ']' ')' '->' FOREIGN_LOCATION '(' '[' TEXTS ']' ')' '->' FOREIGN_LOCATION '(' TEXT ')'");
        $this->FOREIGN_CUSTOM = $this->parser->push("DEFINITION", "FOREIGN '(' TEXT ')' '->' FOREIGN_LOCATION '(' TEXT ')' '->' FOREIGN_LOCATION '(' TEXT ')'");
        $this->FOREIGN_MANY = $this->parser->push("DEFINITION", "FOREIGN '(' '[' TEXTS ']' ')'");
        $this->FOREIGN = $this->parser->push("DEFINITION", "FOREIGN '(' TEXT ')'");
        $this->MODIFIER_MANY = $this->parser->push("DEFINITION", "MODIFIER '(' '[' TEXTS ']' ')'");
        $this->MODIFIER = $this->parser->push("DEFINITION", "MODIFIER '(' TEXT ')'");
        $this->parser->build();


        $this->lex = new Lexer;
        $this->lex->push("\$table", $this->parser->tokenId("TABLE"));
        $this->lex->push("->", $this->parser->tokenId("'->'"));
        $this->lex->push("(bigIncrements|bigInteger|binary|boolean|char|dateTimeTz|dateTime|date|decimal|double|enum|float|foreignId|foreignIdFor|foreignUlid|foreignUuid|geometryCollection|geometry|increments|integer|ipAddress|json|jsonb|lineString|longText|macAddress|mediumIncrements|mediumInteger|mediumText|morphs|multiLineString|multiPoint|multiPolygon|nullableMorphs|nullableTimestamps|nullableUlidMorphs|nullableUuidMorphs|point|polygon|set|smallIncrements|smallInteger|softDeletesTz|softDeletes|string|text|timeTz|time|timestampTz|timestamp|timestampsTz|tinyIncrements|tinyInteger|tinyText|unsignedBigInteger|unsignedDecimal|unsignedInteger|unsignedMediumInteger|unsignedSmallInteger|unsignedTinyInteger|ulidMorphs|uuidMorphs|ulid|uuid|year)", $this->parser->tokenId("TYPE"));
        $this->lex->push("(id|rememberToken|timestamps)", $this->parser->tokenId("NAMELESS_TYPE"));
        $this->lex->push("(unique|nullable)", $this->parser->tokenId("MODIFIER"));
        $this->lex->push(";", $this->parser->tokenId("END"));
        $this->lex->push("(\\\"\w*\\\"|'\w*')", $this->parser->tokenId("TEXT"));
        $this->lex->push("\\(", $this->parser->tokenId("'('"));
        $this->lex->push("\\)", $this->parser->tokenId("')'"));
        $this->lex->push("\\,", $this->parser->tokenId("COMMA"));
        $this->lex->push("\\[", $this->parser->tokenId("'['"));
        $this->lex->push("\\]", $this->parser->tokenId("']'"));
        $this->lex->push("foreign", $this->parser->tokenId("FOREIGN"));
        $this->lex->push("(references|on)", $this->parser->tokenId("FOREIGN_LOCATION"));
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
     * @param string[] $in
     * @param Schema $schema
     */
    public function parse($migration, &$schema)
    {
        // Str::singular('users');
        foreach ($migration as $in) {
            $this->parser->consume($in, $this->lex);
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
                        /**
                         * Code editor gets confused here and doesn't know the function sigilCount
                         * @var $p any
                         */
                        $p = $this->parser;
                        switch ($p->reduceId) {
                            case $this->TYPE:
                                echo "type\n" . $p->sigilCount() . " sigils\n";
                                echo "\ttype: " . $p->sigil(0) . "\n";
                                echo "\tname: " . $p->sigil(2) . "\n\n";
                                break;
                            case $this->NAMELESS_TYPE:
                                echo "nameLessType\n" . $p->sigilCount() . " sigils\n";
                                echo "\ttype: " . $p->sigil(0) . "\n\n";
                                break;
                            case $this->TYPE_WITH_MODIFIER:
                                echo "typeMod\n" . $p->sigilCount() . " sigils\n";
                                echo "\ttype: " . $p->sigil(0) . "\n";
                                echo "\tname: " . $p->sigil(2) . "\n";
                                echo "\tnullable: " . ($p->sigil(5) === "nullable") . "\n";
                                echo "\tunique: " . ($p->sigil(5) === "unique") . "\n\n";
                                break;
                            case $this->TYPE_WITH_TWO_MODIFIER:
                                echo "typeModPlus\n" . $p->sigilCount() . " sigils\n";
                                echo "\ttype: " . $p->sigil(0) . "\n";
                                echo "\tname: " . $p->sigil(2) . "\n";
                                echo "\tnullable: " . ($p->sigil(5) === "nullable" || $p->sigil(9) === "nullable") . "\n";
                                echo "\tunique: " . ($p->sigil(5) === "unique" || $p->sigil(9) === "unique") . "\n\n";
                                break;
                            case $this->MODIFIER:
                                echo "modifier\n" . $p->sigilCount() . " sigils\n";
                                echo "\ttype: " . $p->sigil(0) . "\n";
                                echo "\tname: " . $p->sigil(2) . "\n";
                                break;
                            case $this->MODIFIER_MANY:
                                echo "modifierMany\n" . $p->sigilCount() . " sigils\n";
                                printSigils($p);
                                break;
                            case $this->FOREIGN:
                                echo "foreignZero\n" . $p->sigilCount() . " sigils\n";
                                printSigils($p);
                                break;
                            case $this->FOREIGN_MANY:
                                echo "foreignZeroMany\n" . $p->sigilCount() . " sigils\n";
                                printSigils($p);
                                break;
                            case $this->FOREIGN_CUSTOM:
                                echo "foreign\n" . $p->sigilCount() . " sigils\n";
                                printSigils($p);
                                break;
                            case $this->FOREIGN_CUSTOM_MANY:
                                echo "foreignMany\n" . $p->sigilCount() . " sigils\n";
                                printSigils($p);
                                break;
                        }
                        break;
                }
                $p->advance();
            } while (Parser::ACTION_ACCEPT != $p->action);
        }
    }
}
