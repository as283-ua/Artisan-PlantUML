<?php

namespace As283\ArtisanPlantuml\Core;

use As283\PlantUmlProcessor\Model\ClassMetadata;
use As283\PlantUmlProcessor\Model\Schema;
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
        $this->TYPE = $this->parser->push("SPECS", "TYPE '(' TEXT ')'");
        $this->NAMELESS_TYPE = $this->parser->push("SPECS", "NAMELESS_TYPE '(' ')'");
        $this->FOREIGN_CUSTOM_MANY = $this->parser->push("SPECS", "FOREIGN '(' '[' TEXTS ']' ')' '->' FOREIGN_LOCATION '(' '[' TEXTS ']' ')' '->' FOREIGN_LOCATION '(' TEXT ')'");
        $this->FOREIGN_CUSTOM = $this->parser->push("SPECS", "FOREIGN '(' TEXT ')' '->' FOREIGN_LOCATION '(' TEXT ')' '->' FOREIGN_LOCATION '(' TEXT ')'");
        $this->FOREIGN_MANY = $this->parser->push("SPECS", "FOREIGN '(' '[' TEXTS ']' ')'");
        $this->FOREIGN = $this->parser->push("SPECS", "FOREIGN '(' TEXT ')'");
        $this->MODIFIER_MANY = $this->parser->push("SPECS", "MODIFIER '(' '[' TEXTS ']' ')'");
        $this->MODIFIER = $this->parser->push("SPECS", "MODIFIER '(' TEXT ')'");
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
     * @param string $in
     * @param Schema $schema
     */
    public function parse($in, &$schema)
    {
        // Str::singular('users');
        $this->parser->consume($in, $this->lex);
        $class = new ClassMetadata();
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
                            $classname = self::removeQuotes($this->parser->sigil(2));
                            if (array_key_exists($classname, $schema->classes)) {
                                $class = $schema->classes[$classname];
                            } else {
                                $class->name = $classname;
                            }
                            break;
                        case $this->TYPE:
                            break;
                        case $this->NAMELESS_TYPE:
                            break;
                        case $this->TYPE_WITH_MODIFIER:
                            break;
                        case $this->TYPE_WITH_TWO_MODIFIER:
                            break;
                        case $this->MODIFIER:
                            break;
                        case $this->MODIFIER_MANY:
                            break;
                        case $this->FOREIGN:
                            break;
                        case $this->FOREIGN_MANY:
                            break;
                        case $this->FOREIGN_CUSTOM:
                            break;
                        case $this->FOREIGN_CUSTOM_MANY:
                            break;
                    }
                    break;
            }
            $this->parser->advance();
        } while (Parser::ACTION_ACCEPT != $this->parser->action);
        $schema->classes[$class->name] = $class;
    }

    private static function removeQuotes($str)
    {
        return trim($str, "\"'");
    }
}
