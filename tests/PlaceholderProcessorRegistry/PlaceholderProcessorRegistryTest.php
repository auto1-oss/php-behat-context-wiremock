<?php

namespace Auto1\BehatContext\Tests\PlaceholderProcessorRegistry;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\PlaceholderProcessorInterface;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessorRegistry;
use PHPUnit\Framework\TestCase;

class PlaceholderProcessorRegistryTest extends TestCase
{
    private PlaceholderProcessorInterface $mockProcessor1;
    private PlaceholderProcessorInterface $mockProcessor2;
    private PlaceholderProcessorInterface $mockProcessor3;

    protected function setUp(): void
    {
        $this->mockProcessor1 = $this->createMock(PlaceholderProcessorInterface::class);
        $this->mockProcessor1->method('getName')->willReturn('processor_one');

        $this->mockProcessor2 = $this->createMock(PlaceholderProcessorInterface::class);
        $this->mockProcessor2->method('getName')->willReturn('processor_two');

        $this->mockProcessor3 = $this->createMock(PlaceholderProcessorInterface::class);
        $this->mockProcessor3->method('getName')->willReturn('processor_three');
    }

    public function testConstructorWithEmptyArray(): void
    {
        $registry = new PlaceholderProcessorRegistry([]);

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Unsupported processor "nonexistent"');

        $registry->getProcessor('nonexistent');
    }

    public function testConstructorWithSingleProcessor(): void
    {
        $registry = new PlaceholderProcessorRegistry([$this->mockProcessor1]);

        $result = $registry->getProcessor('processor_one');

        $this->assertSame($this->mockProcessor1, $result);
    }

    public function testConstructorWithMultipleProcessors(): void
    {
        $registry = new PlaceholderProcessorRegistry([
            $this->mockProcessor1,
            $this->mockProcessor2,
            $this->mockProcessor3
        ]);

        $this->assertSame($this->mockProcessor1, $registry->getProcessor('processor_one'));
        $this->assertSame($this->mockProcessor2, $registry->getProcessor('processor_two'));
        $this->assertSame($this->mockProcessor3, $registry->getProcessor('processor_three'));
    }

    public function testGetProcessorReturnsCorrectProcessor(): void
    {
        $registry = new PlaceholderProcessorRegistry([
            $this->mockProcessor1,
            $this->mockProcessor2
        ]);

        $result = $registry->getProcessor('processor_two');

        $this->assertSame($this->mockProcessor2, $result);
        $this->assertNotSame($this->mockProcessor1, $result);
    }

    public function testGetProcessorThrowsExceptionForNonexistentProcessor(): void
    {
        $registry = new PlaceholderProcessorRegistry([
            $this->mockProcessor1,
            $this->mockProcessor2
        ]);

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Unsupported processor "nonexistent_processor"');

        $registry->getProcessor('nonexistent_processor');
    }

    public function testGetProcessorThrowsExceptionForEmptyString(): void
    {
        $registry = new PlaceholderProcessorRegistry([$this->mockProcessor1]);

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Unsupported processor ""');

        $registry->getProcessor('');
    }

    public function testGetProcessorIsCaseSensitive(): void
    {
        $registry = new PlaceholderProcessorRegistry([$this->mockProcessor1]);

        $this->assertSame($this->mockProcessor1, $registry->getProcessor('processor_one'));

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Unsupported processor "Processor_One"');

        $registry->getProcessor('Processor_One');
    }

    public function testProcessorWithValidSpecialCharactersInName(): void
    {
        $specialProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $specialProcessor->method('getName')->willReturn('special_processor_with_underscores');

        $registry = new PlaceholderProcessorRegistry([$specialProcessor]);

        $result = $registry->getProcessor('special_processor_with_underscores');

        $this->assertSame($specialProcessor, $result);
    }

    public function testProcessorWithValidNumericName(): void
    {
        $numericProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $numericProcessor->method('getName')->willReturn('processor123');

        $registry = new PlaceholderProcessorRegistry([$numericProcessor]);

        $result = $registry->getProcessor('processor123');

        $this->assertSame($numericProcessor, $result);
    }

    public function testProcessorWithEmptyNameThrowsExceptionDuringConstruction(): void
    {
        $emptyNameProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $emptyNameProcessor->method('getName')->willReturn('');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Processor name cannot be empty');

        new PlaceholderProcessorRegistry([$emptyNameProcessor]);
    }

    public function testDuplicateProcessorNamesThrowsException(): void
    {
        $processor1 = $this->createMock(PlaceholderProcessorInterface::class);
        $processor1->method('getName')->willReturn('duplicate_name');

        $processor2 = $this->createMock(PlaceholderProcessorInterface::class);
        $processor2->method('getName')->willReturn('duplicate_name');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Duplicate processor name "duplicate_name" detected');

        new PlaceholderProcessorRegistry([$processor1, $processor2]);
    }

    public function testConstructorWithDefaultEmptyArray(): void
    {
        $registry = new PlaceholderProcessorRegistry();

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Unsupported processor "any_processor"');

        $registry->getProcessor('any_processor');
    }

    public function testEmptyNameValidationWithMultipleProcessors(): void
    {
        $validProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $validProcessor->method('getName')->willReturn('valid_processor');

        $emptyNameProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $emptyNameProcessor->method('getName')->willReturn('');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Processor name cannot be empty');

        new PlaceholderProcessorRegistry([$validProcessor, $emptyNameProcessor]);
    }

    public function testInvalidProcessorNameWithUppercase(): void
    {
        $invalidProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $invalidProcessor->method('getName')->willReturn('Processor_Name');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid processor name "Processor_Name". Processor names must start with a letter, contain only lowercase letters, numbers, underscores, and dots, and end with a letter or number.');

        new PlaceholderProcessorRegistry([$invalidProcessor]);
    }

    public function testInvalidProcessorNameWithSpaces(): void
    {
        $invalidProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $invalidProcessor->method('getName')->willReturn('processor with spaces');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid processor name "processor with spaces". Processor names must start with a letter, contain only lowercase letters, numbers, underscores, and dots, and end with a letter or number.');

        new PlaceholderProcessorRegistry([$invalidProcessor]);
    }

    public function testInvalidProcessorNameWithDashes(): void
    {
        $invalidProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $invalidProcessor->method('getName')->willReturn('processor-name-with-dashes');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid processor name "processor-name-with-dashes". Processor names must start with a letter, contain only lowercase letters, numbers, underscores, and dots, and end with a letter or number.');

        new PlaceholderProcessorRegistry([$invalidProcessor]);
    }

    public function testInvalidProcessorNameStartingWithNumber(): void
    {
        $invalidProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $invalidProcessor->method('getName')->willReturn('123processor');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid processor name "123processor". Processor names must start with a letter, contain only lowercase letters, numbers, underscores, and dots, and end with a letter or number.');

        new PlaceholderProcessorRegistry([$invalidProcessor]);
    }

    public function testInvalidProcessorNameStartingWithUnderscore(): void
    {
        $invalidProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $invalidProcessor->method('getName')->willReturn('_processor');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid processor name "_processor". Processor names must start with a letter, contain only lowercase letters, numbers, underscores, and dots, and end with a letter or number.');

        new PlaceholderProcessorRegistry([$invalidProcessor]);
    }

    public function testInvalidProcessorNameEndingWithUnderscore(): void
    {
        $invalidProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $invalidProcessor->method('getName')->willReturn('processor_');

        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Invalid processor name "processor_". Processor names must start with a letter, contain only lowercase letters, numbers, underscores, and dots, and end with a letter or number.');

        new PlaceholderProcessorRegistry([$invalidProcessor]);
    }

    public function testValidProcessorNameFormats(): void
    {
        $validNames = [
            'a',
            'ab',
            'processor',
            'processor_name',
            'processor123',
            'a1b2c3',
            'flatten_text',
            'json_to_url_encoded_query_string',
            'processor.name',
            'namespace.processor',
            'my.custom.processor123',
            'a.b.c'
        ];

        foreach ($validNames as $name) {
            $processor = $this->createMock(PlaceholderProcessorInterface::class);
            $processor->method('getName')->willReturn($name);

            $registry = new PlaceholderProcessorRegistry([$processor]);
            $result = $registry->getProcessor($name);
            $this->assertSame($processor, $result);
        }
    }

    public function testInvalidProcessorNameFormats(): void
    {
        $invalidNames = [
            'A',
            'Processor',
            'processor-name',
            'processor name',
            '123',
            '_processor',
            'processor_',
            'processor@name',
            'processor#name',
            'PROCESSOR',
            'processör',
        ];

        foreach ($invalidNames as $name) {
            $processor = $this->createMock(PlaceholderProcessorInterface::class);
            $processor->method('getName')->willReturn($name);

            try {
                new PlaceholderProcessorRegistry([$processor]);
                $this->fail(sprintf("Expected exception for invalid name: %s", $name));
            } catch (WiremockContextException $e) {
                $this->assertStringContainsString(sprintf('Invalid processor name "%s"', $name), $e->getMessage());
            }
        }
    }

    public function testValidProcessorNameWithDots(): void
    {
        $dotProcessor = $this->createMock(PlaceholderProcessorInterface::class);
        $dotProcessor->method('getName')->willReturn('namespace.processor_name');

        $registry = new PlaceholderProcessorRegistry([$dotProcessor]);

        $result = $registry->getProcessor('namespace.processor_name');

        $this->assertSame($dotProcessor, $result);
    }
}
