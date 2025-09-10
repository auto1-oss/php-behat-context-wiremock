<?php

namespace Auto1\BehatContext\Tests\PlaceholderParser;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
use Auto1\BehatContext\Wiremock\PlaceholderParser\PlaceholderParser;
use PHPUnit\Framework\TestCase;

class PlaceholderParserTest extends TestCase
{
    private PlaceholderParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PlaceholderParser();
    }

    public function testParseSimpleArguments(): void
    {
        $result = $this->parser->parse("'a', 'b', 'c'");
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testParseNestedArray(): void
    {
        $result = $this->parser->parse("['*', ['d' => 'f']]");
        $expectedArray = [
            ['*', ['d' => 'f']]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseAssociativeArray(): void
    {
        $result = $this->parser->parse("['key1' => 'value1', 'key2' => 'value2']");
        $expectedArray = [
            ['key1' => 'value1', 'key2' => 'value2']
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseComplexNestedStructure(): void
    {
        $result = $this->parser->parse("['simple_value', ['nested' => ['deep' => 'value']], ['a', 'b', 'c'], 'another_value']");
        $expectedArray = [
            [
                'simple_value',
                ['nested' => ['deep' => 'value']],
                ['a', 'b', 'c'],
                'another_value'
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseArrayWithDifferentDataTypes(): void
    {
        $result = $this->parser->parse("['string_value', 42, 3.14, true, false, null, ['nested' => 123]]");
        $expectedArray = [
            [
                'string_value',
                42,
                3.14,
                true,
                false,
                null,
                ['nested' => 123]
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseArrayWithQuotedStrings(): void
    {
        $result = $this->parser->parse("['quoted string', \"double quoted\", ['key with spaces' => 'value with spaces']]");
        $expectedArray = [
            [
                'quoted string',
                "double quoted",
                ['key with spaces' => 'value with spaces']
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseEmptyArray(): void
    {
        $result = $this->parser->parse("[]");
        $this->assertEquals([[]], $result);
    }

    public function testParseEmptyString(): void
    {
        $result = $this->parser->parse("");
        $this->assertEquals([], $result);
    }

    public function testParseArrayWithSpecialCharacters(): void
    {
        $result = $this->parser->parse("['@#\$%^&*()', ['special' => '!@#\$%^&*()']]");
        $expectedArray = [
            [
                '@#$%^&*()',
                ['special' => '!@#$%^&*()']
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseArrayWithEscapedQuotes(): void
    {
        $result = $this->parser->parse("['string with \"quotes\"', ['key' => 'value with \\'single quotes\\'']]");
        $expectedArray = [
            [
                'string with "quotes"',
                ['key' => 'value with \'single quotes\'']
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseMultipleArguments(): void
    {
        $result = $this->parser->parse("'first_arg', ['*', ['d' => 'f']], 'third_arg'");
        $expectedArray = [
            'first_arg',
            ['*', ['d' => 'f']],
            'third_arg'
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseInvalidAssociativeArraySyntax(): void
    {
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid associative array syntax: use "=>" instead of "="');

        $this->parser->parse("['key' = 'value']");
    }

    public function testParseInvalidArrayKey(): void
    {
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Array keys must be strings or integers');

        $this->parser->parse("[true => 'value']");
    }

    public function testParseArrayWithIntegerKeys(): void
    {
        $result = $this->parser->parse("[0 => 'first', 1 => 'second', 5 => 'fifth']");
        $expectedArray = [
            [
                0 => 'first',
                1 => 'second',
                5 => 'fifth'
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseDeepNestedArray(): void
    {
        $result = $this->parser->parse("['level1' => ['level2' => ['level3' => ['level4' => 'deep_value']]]]");
        $expectedArray = [
            [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => 'deep_value'
                        ]
                    ]
                ]
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseArrayWithCommasInQuotes(): void
    {
        $result = $this->parser->parse("['value, with, commas', ['key' => 'another, value, with, commas']]");
        $expectedArray = [
            [
                'value, with, commas',
                ['key' => 'another, value, with, commas']
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseArrayWithBracketsInQuotes(): void
    {
        $result = $this->parser->parse("['value [with] brackets', ['key' => 'another [value] with [brackets]']]");
        $expectedArray = [
            [
                'value [with] brackets',
                ['key' => 'another [value] with [brackets]']
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseScalarValues(): void
    {
        $result = $this->parser->parse("42");
        $this->assertEquals([42], $result);

        $result = $this->parser->parse("3.14");
        $this->assertEquals([3.14], $result);

        $result = $this->parser->parse("true");
        $this->assertEquals([true], $result);

        $result = $this->parser->parse("false");
        $this->assertEquals([false], $result);

        $result = $this->parser->parse("null");
        $this->assertEquals([null], $result);

        $result = $this->parser->parse("'string'");
        $this->assertEquals(['string'], $result);
    }

    public function testParseUnquotedString(): void
    {
        $result = $this->parser->parse("unquoted_string");
        $this->assertEquals(['unquoted_string'], $result);
    }

    public function testParseMixedArgumentTypes(): void
    {
        $result = $this->parser->parse("'string', 42, true, ['array' => 'value'], null");
        $expectedArray = [
            'string',
            42,
            true,
            ['array' => 'value'],
            null
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseArrayWithWhitespace(): void
    {
        $result = $this->parser->parse("  [  'key'  =>  'value'  ,  'another'  =>  42  ]  ");
        $expectedArray = [
            ['key' => 'value', 'another' => 42]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseNestedArraysWithMixedTypes(): void
    {
        $result = $this->parser->parse("['outer' => ['inner' => [1, 2, 3]], 'simple' => 'value']");
        $expectedArray = [
            [
                'outer' => ['inner' => [1, 2, 3]],
                'simple' => 'value'
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseArrayWithFloatKeys(): void
    {
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Array keys must be strings or integers');

        $this->parser->parse("[3.14 => 'value']");
    }

    public function testParseArrayWithNullKeys(): void
    {
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Array keys must be strings or integers');

        $this->parser->parse("[null => 'value']");
    }

    public function testParseMultipleMalformedAssociativeElements(): void
    {
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid associative array syntax: use "=>" instead of "="');

        $this->parser->parse("['key1' = 'value1', 'key2' = 'value2']");
    }

    public function testParseMalformedAssociativeWithUnquotedKey(): void
    {
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid associative array syntax: use "=>" instead of "="');

        $this->parser->parse("[key = 'value']");
    }

    public function testParseMalformedAssociativeWithNumericKey(): void
    {
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid associative array syntax: use "=>" instead of "="');

        $this->parser->parse("[123 = 'value']");
    }

    public function testParseValidEqualsInQuotes(): void
    {
        // Test that '=' inside quotes is not treated as malformed syntax
        $result = $this->parser->parse("['equation = 2+2', 'another = string']");
        $expectedArray = [
            [
                'equation = 2+2',
                'another = string'
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }

    public function testParseValidEqualsInNestedArray(): void
    {
        // Test that '=' inside nested arrays doesn't interfere with outer array parsing
        $result = $this->parser->parse("['valid' => ['inner = string'], 'another' => 'value']");
        $expectedArray = [
            [
                'valid' => ['inner = string'],
                'another' => 'value'
            ]
        ];
        $this->assertEquals($expectedArray, $result);
    }
}
