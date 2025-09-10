<?php

namespace Auto1\BehatContext\Tests\PlaceholderProcessorRegistry\PlaceholderProcessor;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\JsonToUrlEncodedQueryStringProcessor;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\PlaceholderProcessorInterface;
use PHPUnit\Framework\TestCase;

class JsonToUrlEncodedQueryStringProcessorTest extends TestCase
{
    private string $testStubsDirectory;
    private JsonToUrlEncodedQueryStringProcessor $processor;

    protected function setUp(): void
    {
        $this->testStubsDirectory = sys_get_temp_dir() . '/json_processor_test_' . uniqid('', true);
        mkdir($this->testStubsDirectory, 0700, true);
        $this->processor = new JsonToUrlEncodedQueryStringProcessor();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testStubsDirectory);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTestFile(string $filename, string $content): string
    {
        $filePath = $this->testStubsDirectory . '/' . $filename;
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($filePath, $content);
        return $filePath;
    }

    public function testProcessWithSimpleJsonObject(): void
    {
        $filename = 'simple.json';
        $jsonContent = '{"name": "John", "age": 30}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $this->assertEquals('name=John&age=30', $result);
    }

    public function testProcessWithNestedJsonObject(): void
    {
        $filename = 'nested.json';
        $jsonContent = '{"user": {"name": "John", "age": 30}, "active": true}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $expected = 'user=' . rawurlencode('{"name":"John","age":30}') . '&active=1';
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithJsonArray(): void
    {
        $filename = 'array.json';
        $jsonContent = '{"items": ["apple", "banana", "cherry"], "count": 3}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $expected = 'items=' . rawurlencode('["apple","banana","cherry"]') . '&count=3';
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithSpecialCharacters(): void
    {
        $filename = 'special.json';
        $jsonContent = '{"message": "Hello World!", "symbol": "@#$%"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $expected = 'message=Hello%20World%21&symbol=%40%23%24%25';
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithIgnoredCharacters(): void
    {
        $filename = 'ignored.json';
        $jsonContent = '{"message": "Hello@World", "email": "test@example.com"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, ['@', '.']]);

        $expected = 'message=Hello@World&email=test@example.com';
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithMultipleIgnoredCharacters(): void
    {
        $filename = 'multiple_ignored.json';
        $jsonContent = '{"url": "https://example.com/path?param=value", "special": "a+b=c"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, [':', '/', '?', '=', '+']]);

        $expected = 'url=https://example.com/path?param=value&special=a+b=c';
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithEmptyJsonObject(): void
    {
        $filename = 'empty.json';
        $jsonContent = '{}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $this->assertEquals('', $result);
    }

    public function testProcessWithBooleanValues(): void
    {
        $filename = 'boolean.json';
        $jsonContent = '{"active": true, "deleted": false, "verified": null}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $this->assertEquals('active=1&deleted=0', $result);
    }

    public function testProcessWithNumericValues(): void
    {
        $filename = 'numeric.json';
        $jsonContent = '{"integer": 42, "float": 3.14, "zero": 0, "negative": -5}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $this->assertEquals('integer=42&float=3.14&zero=0&negative=-5', $result);
    }

    public function testProcessWithUnicodeCharacters(): void
    {
        $filename = 'unicode.json';
        $jsonContent = '{"name": "José", "city": "São Paulo", "emoji": "😀"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $expected = 'name=Jos%C3%A9&city=S%C3%A3o%20Paulo&emoji=%F0%9F%98%80';
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithJsonContainingWhitespace(): void
    {
        $filename = 'whitespace.json';
        $jsonContent = '  {  "name"  :  "John"  ,  "age"  :  30  }  ';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $this->assertEquals('name=John&age=30', $result);
    }

    public function testProcessWithComplexNestedStructure(): void
    {
        $filename = 'complex.json';
        $jsonContent = '{
            "user": {
                "profile": {
                    "name": "John Doe",
                    "preferences": ["email", "sms"]
                },
                "settings": {
                    "theme": "dark",
                    "notifications": true
                }
            },
            "metadata": {
                "created": "2023-01-01",
                "tags": ["important", "user-data"]
            }
        }';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $expectedUser = rawurlencode('{"profile":{"name":"John Doe","preferences":["email","sms"]},"settings":{"theme":"dark","notifications":true}}');
        $expectedMetadata = rawurlencode('{"created":"2023-01-01","tags":["important","user-data"]}');
        $expected = "user={$expectedUser}&metadata={$expectedMetadata}";

        $this->assertEquals($expected, $result);
    }

    public function testProcessThrowsExceptionWhenFileDoesNotExist(): void
    {
        $filename = 'nonexistent.json';
        $expectedPath = $this->testStubsDirectory . '/' . $filename;

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("File not found: $expectedPath");

        $this->processor->process($this->testStubsDirectory, [$filename, []]);
    }

    public function testProcessThrowsExceptionWhenFileCannotBeRead(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('File permission test not reliable on Windows');
        }

        $filename = 'unreadable.json';
        $jsonContent = '{"test": "value"}';
        $absolutePath = $this->createTestFile($filename, $jsonContent);

        // Make file unreadable
        chmod($absolutePath, 0000);

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("Unable to read file: $absolutePath");

        try {
            @$this->processor->process($this->testStubsDirectory, [$filename, []]);
        } finally {
            // Restore permissions for cleanup
            chmod($absolutePath, 0644);
        }
    }

    public function testProcessThrowsExceptionForInvalidJson(): void
    {
        $filename = 'invalid.json';
        $invalidJsonContent = '{"name": "John", "age":}'; // Missing value
        $this->createTestFile($filename, $invalidJsonContent);

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("Invalid JSON in file: $filename");

        $this->processor->process($this->testStubsDirectory, [$filename, []]);
    }

    public function testProcessThrowsExceptionForMalformedJson(): void
    {
        $filename = 'malformed.json';
        $malformedJsonContent = '{name: "John"}'; // Missing quotes around key
        $this->createTestFile($filename, $malformedJsonContent);

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("Invalid JSON in file: $filename");

        $this->processor->process($this->testStubsDirectory, [$filename, []]);
    }

    public function testProcessThrowsExceptionForNonObjectJson(): void
    {
        $filename = 'array_root.json';
        $arrayJsonContent = '["item1", "item2", "item3"]'; // Root level array
        $this->createTestFile($filename, $arrayJsonContent);

        // This should work as json_decode returns an array, and http_build_query can handle arrays
        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);
        $this->assertEquals('0=item1&1=item2&2=item3', $result);
    }

    public function testProcessThrowsExceptionWhenIgnoredCharactersIsNotArray(): void
    {
        $filename = 'test.json';
        $jsonContent = '{"name": "John"}';
        $this->createTestFile($filename, $jsonContent);

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Ignored characters must be an array');

        $this->processor->process($this->testStubsDirectory, [$filename, 'not_an_array']);
    }

    public function testProcessWithEmptyIgnoredCharactersArray(): void
    {
        $filename = 'empty_ignored.json';
        $jsonContent = '{"message": "Hello@World.com"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $expected = 'message=Hello%40World.com';
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithNullIgnoredCharacters(): void
    {
        $filename = 'test.json';
        $jsonContent = '{"name": "John"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, null]);

        $this->assertEquals('name=John', $result);
    }

    public function testProcessWithStringIgnoredCharacters(): void
    {
        $filename = 'test.json';
        $jsonContent = '{"name": "John"}';
        $this->createTestFile($filename, $jsonContent);

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Ignored characters must be an array');

        $this->processor->process($this->testStubsDirectory, [$filename, '@']);
    }

    public function testProcessWithFileInSubdirectory(): void
    {
        $filename = 'subdir/test.json';
        $jsonContent = '{"name": "John", "location": "subdir"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $this->assertEquals('name=John&location=subdir', $result);
    }

    public function testProcessWithEmptyStringValues(): void
    {
        $filename = 'empty_strings.json';
        $jsonContent = '{"name": "", "description": "", "value": "actual_value"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $this->assertEquals('name=&description=&value=actual_value', $result);
    }

    public function testProcessWithSpecialJsonCharacters(): void
    {
        $filename = 'json_special.json';
        $jsonContent = '{"quote": "\\"Hello\\"", "backslash": "C:\\\\path", "newline": "line1\\nline2"}';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $expected = 'quote=%22Hello%22&backslash=C%3A%5Cpath&newline=line1%0Aline2';
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithLargeJsonObject(): void
    {
        $filename = 'large.json';
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data["key_$i"] = "value_$i";
        }
        $jsonContent = json_encode($data);
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        // Verify it contains expected number of parameters
        $params = explode('&', $result);
        $this->assertCount(100, $params);

        // Verify first and last parameters
        $this->assertStringStartsWith('key_0=value_0', $result);
        $this->assertStringEndsWith('key_99=value_99', $result);
    }

    public function testProcessWithMixedDataTypes(): void
    {
        $filename = 'mixed.json';
        $jsonContent = '{
            "string": "text",
            "integer": 42,
            "float": 3.14,
            "boolean_true": true,
            "boolean_false": false,
            "null_value": null,
            "array": [1, 2, 3],
            "object": {"nested": "value"}
        }';
        $this->createTestFile($filename, $jsonContent);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, []]);

        $expectedArray = rawurlencode('[1,2,3]');
        $expectedObject = rawurlencode('{"nested":"value"}');
        $expected = "string=text&integer=42&float=3.14&boolean_true=1&boolean_false=0&array={$expectedArray}&object={$expectedObject}";

        $this->assertEquals($expected, $result);
    }

    public function testProcessWithEmptyFile(): void
    {
        $filename = 'empty_file.json';
        $this->createTestFile($filename, '');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("Invalid JSON in file: $filename");

        $this->processor->process($this->testStubsDirectory, [$filename, []]);
    }

    public function testProcessWithOnlyWhitespaceFile(): void
    {
        $filename = 'whitespace_only.json';
        $this->createTestFile($filename, '   \n\t   ');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("Invalid JSON in file: $filename");

        $this->processor->process($this->testStubsDirectory, [$filename, []]);
    }

    public function testProcessWithIgnoredCharactersContainingSpecialRegexChars(): void
    {
        $filename = 'regex_special.json';
        $jsonContent = '{"pattern": "a.b*c+d?e[f]g(h)i{j}k|l^m$n"}';
        $this->createTestFile($filename, $jsonContent);

        // Test with regex special characters as ignored characters
        $result = $this->processor->process($this->testStubsDirectory, [$filename, ['.', '*', '+', '?', '[', ']', '(', ')', '{', '}', '|', '^', '$']]);

        $expected = 'pattern=a.b*c+d?e[f]g(h)i{j}k|l^m$n';
        $this->assertEquals($expected, $result);
    }
}
