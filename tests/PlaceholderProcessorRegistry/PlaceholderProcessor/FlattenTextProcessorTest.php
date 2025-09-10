<?php

namespace Auto1\BehatContext\Tests\PlaceholderProcessorRegistry\PlaceholderProcessor;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\FlattenTextProcessor;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\PlaceholderProcessorInterface;
use PHPUnit\Framework\TestCase;

class FlattenTextProcessorTest extends TestCase
{
    private string $testStubsDirectory;
    private FlattenTextProcessor $processor;
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->testStubsDirectory = sys_get_temp_dir() . '/flatten_text_processor_test_' . uniqid('', true);
        mkdir($this->testStubsDirectory, 0700, true);
        $this->processor = new FlattenTextProcessor();
        $this->createdFiles = [];
    }

    protected function tearDown(): void
    {
        // Clean up files created in current directory
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

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

    private function createTestFileInStubsDir(string $filename, string $content): string
    {
        $filePath = $this->testStubsDirectory . '/' . $filename;
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($filePath, $content);
        return $filePath;
    }

    public function testProcessWithValidFileAndSimpleContent(): void
    {
        $filename = 'test.txt';
        $content = 'Hello World';
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Hello World', $result);
    }

    public function testProcessWithMultipleSpaces(): void
    {
        $filename = 'spaces.txt';
        $content = 'Hello    World    Test';
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Hello World Test', $result);
    }

    public function testProcessWithNewlines(): void
    {
        $filename = 'newlines.txt';
        $content = "Hello\nWorld\nTest";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Hello World Test', $result);
    }

    public function testProcessWithTabs(): void
    {
        $filename = 'tabs.txt';
        $content = "Hello\tWorld\tTest";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Hello World Test', $result);
    }

    public function testProcessWithMixedWhitespace(): void
    {
        $filename = 'mixed.txt';
        $content = "Hello   \n\t  World\r\n\t\t  Test   \n";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Hello World Test', $result);
    }

    public function testProcessWithLeadingAndTrailingWhitespace(): void
    {
        $filename = 'trim.txt';
        $content = "   \n\t  Hello World  \n\t   ";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Hello World', $result);
    }

    public function testProcessWithEmptyFile(): void
    {
        $filename = 'empty.txt';
        $content = '';
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('', $result);
    }

    public function testProcessWithOnlyWhitespace(): void
    {
        $filename = 'whitespace.txt';
        $content = "   \n\t\r\n   ";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('', $result);
    }

    public function testProcessWithSingleCharacter(): void
    {
        $filename = 'single.txt';
        $content = 'A';
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('A', $result);
    }

    public function testProcessWithSpecialCharacters(): void
    {
        $filename = 'special.txt';
        $content = "Hello\n@#$%^&*()  World\t!";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Hello @#$%^&*() World !', $result);
    }

    public function testProcessWithUnicodeCharacters(): void
    {
        $filename = 'unicode.txt';
        $content = "Héllo   Wörld\n测试";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Héllo Wörld 测试', $result);
    }

    public function testProcessThrowsExceptionWhenFileDoesNotExist(): void
    {
        $filename = 'nonexistent.txt';
        $expectedPath = $this->testStubsDirectory . '/' . $filename;

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("File not found: $expectedPath");

        $this->processor->process($this->testStubsDirectory, [$filename]);
    }

    public function testProcessWithAbsolutePathInArgs(): void
    {
        $filename = 'absolute_test.txt';
        $content = 'Absolute path test';
        $absolutePath = $this->createTestFileInStubsDir($filename, $content);

        // Test using absolute path as filename argument - this will cause double path concatenation
        $expectedPath = $this->testStubsDirectory . '/' . $absolutePath;
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("File not found: $expectedPath");

        $this->processor->process($this->testStubsDirectory, [$absolutePath]);
    }

    public function testProcessWithNumericArgument(): void
    {
        $filename = '123';
        $content = 'Numeric filename test';
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Numeric filename test', $result);
    }

    public function testProcessWithMultipleArguments(): void
    {
        // The processor uses first two arguments: stubsDirectory and filename
        $filename = 'first.txt';
        $content = 'First file content';
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename, 'third.txt']);

        $this->assertEquals('First file content', $result);
    }

    public function testProcessWithLargeFile(): void
    {
        $filename = 'large.txt';
        $content = str_repeat("Line with spaces   and\ttabs\n", 1000);
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $expected = trim(str_repeat('Line with spaces and tabs ', 1000));
        $this->assertEquals($expected, $result);
    }

    public function testProcessWithFileContainingOnlyNewlines(): void
    {
        $filename = 'newlines_only.txt';
        $content = "\n\n\n\n";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('', $result);
    }

    public function testProcessWithFileContainingConsecutiveWhitespaceTypes(): void
    {
        $filename = 'consecutive.txt';
        $content = "Word1 \t\n\r Word2";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Word1 Word2', $result);
    }

    public function testProcessPreservesNonWhitespaceCharacters(): void
    {
        $filename = 'preserve.txt';
        $content = "a1!@#$%^&*()_+-={}[]|\\:;\"'<>?,./`~";
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals("a1!@#$%^&*()_+-={}[]|\\:;\"'<>?,./`~", $result);
    }

    public function testImplementsPlaceholderProcessorInterface(): void
    {
        $this->assertInstanceOf(PlaceholderProcessorInterface::class, $this->processor);
    }

    public function testGetNameReturnsCorrectName(): void
    {
        $this->assertEquals('flatten_text', $this->processor->getName());
    }


    /**
     * Test with subdirectory file
     */
    public function testProcessWithFileInSubdirectory(): void
    {
        $filename = 'subdir/test.txt';
        $content = 'Subdirectory file content';
        $this->createTestFileInStubsDir($filename, $content);

        $result = $this->processor->process($this->testStubsDirectory, [$filename]);

        $this->assertEquals('Subdirectory file content', $result);
    }
}
