<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Tests;

use PhpMyAdmin\SqlParser\Context;
use PhpMyAdmin\SqlParser\Exceptions\LexerException;
use PhpMyAdmin\SqlParser\Exceptions\ParserException;
use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokensList;
use PhpMyAdmin\SqlParser\Tools\CustomJsonSerializer;
use PHPUnit\Framework\TestCase as BaseTestCase;

use function file_get_contents;
use function str_contains;
use function strpos;
use function substr;

/**
 * Implements useful methods for testing.
 */
abstract class TestCase extends BaseTestCase
{
    public function setUp(): void
    {
        // This line makes sure the test suite uses English so we can assert
        // on the error messages, if it is not here you will need to use
        // LC_ALL=C ./vendor/bin/phpunit
        // Users can have French language as default on their OS
        // That would make the assertions fail
        $GLOBALS['lang'] = 'en';
        Context::load();
    }

    /**
     * Gets the token list generated by lexing this query.
     *
     * @param string $query the query to be lexed
     */
    public function getTokensList(string $query): TokensList
    {
        $lexer = new Lexer($query);

        return $lexer->list;
    }

    /**
     * Gets the errors as an array.
     *
     * @param Lexer|Parser $obj object containing the errors
     *
     * @return array<int, array<int, Token|string|int>>
     * @psalm-return (
     *     $obj is Lexer
     *     ? list<array{string, string, int, int}>
     *     : list<array{string, Token|null, int}>
     * )
     */
    public function getErrorsAsArray(Lexer|Parser $obj): array
    {
        $ret = [];
        if ($obj instanceof Lexer) {
            /** @var LexerException $err */
            foreach ($obj->errors as $err) {
                $ret[] = [$err->getMessage(), $err->ch, $err->pos, (int) $err->getCode()];
            }
        } elseif ($obj instanceof Parser) {
            /** @var ParserException $err */
            foreach ($obj->errors as $err) {
                $ret[] = [$err->getMessage(), $err->token, (int) $err->getCode()];
            }
        }

        return $ret;
    }

    /**
     * Gets test's input and expected output.
     *
     * @param string $name the name of the test
     *
     * @return array<string, string|Lexer|Parser|array<string, array<int, int|string|Token>[]>|null>
     * @psalm-return array{
     *   query: string,
     *   lexer: Lexer,
     *   parser: Parser|null,
     *   errors: array{lexer: list<array{string, string, int, int}>, parser: list<array{string, Token, int}>}
     * }
     */
    public function getData(string $name): array
    {
        $serializedData = file_get_contents('tests/data/' . $name . '.out');
        $this->assertIsString($serializedData);

        $serializer = new CustomJsonSerializer();
        $data = $serializer->unserialize($serializedData);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('query', $data);
        $this->assertArrayHasKey('lexer', $data);
        $this->assertArrayHasKey('parser', $data);
        $this->assertArrayHasKey('errors', $data);
        $this->assertIsString($data['query']);
        $this->assertInstanceOf(Lexer::class, $data['lexer']);
        if ($data['parser'] !== null) {
            $this->assertInstanceOf(Parser::class, $data['parser']);
        }

        $this->assertIsArray($data['errors']);
        $this->assertArrayHasKey('lexer', $data['errors']);
        $this->assertArrayHasKey('parser', $data['errors']);
        $this->assertIsArray($data['errors']['lexer']);
        $this->assertIsArray($data['errors']['parser']);

        $data['query'] = file_get_contents('tests/data/' . $name . '.in');
        $this->assertIsString($data['query']);

        return $data;
    }

    /**
     * Runs a test.
     *
     * @param string $name the name of the test
     */
    public function runParserTest(string $name): void
    {
        /**
         * Test's data.
         */
        $data = $this->getData($name);

        if (str_contains($name, '/ansi/')) {
            // set mode if appropriate
            Context::setMode(Context::SQL_MODE_ANSI_QUOTES);
        }

        $mariaDbPos = strpos($name, '_mariadb_');
        if ($mariaDbPos !== false) {// Keep in sync with TestGenerator.php
            // set context
            $mariaDbVersion = (int) substr($name, $mariaDbPos + 9, 6);
            Context::load('MariaDb' . $mariaDbVersion);
        }

        // Lexer.
        $lexer = new Lexer($data['query']);
        $lexerErrors = $this->getErrorsAsArray($lexer);
        $lexer->errors = [];

        // Parser.
        $parser = empty($data['parser']) ? null : new Parser($lexer->list);
        $parserErrors = [];
        if ($parser !== null) {
            $parserErrors = $this->getErrorsAsArray($parser);
            $parser->errors = [];
        }

        // Testing objects.
        $this->assertEquals($data['lexer'], $lexer);
        $this->assertEquals($data['parser'], $parser);

        // Testing errors.
        $this->assertEquals($data['errors']['parser'], $parserErrors);
        $this->assertEquals($data['errors']['lexer'], $lexerErrors);

        // reset mode after test run
        Context::setMode();
    }
}
