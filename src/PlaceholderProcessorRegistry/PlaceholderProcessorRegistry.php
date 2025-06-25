<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\PlaceholderProcessorInterface;

class PlaceholderProcessorRegistry
{
    /**
     * @var array<string, PlaceholderProcessorInterface>
     */
    private array $indexedProcessors = [];

    /**
     * @param PlaceholderProcessorInterface[] $processors
     */
    public function __construct(private array $processors = [])
    {
        $this->indexByName();
    }

    /**
     * @throws WiremockContextException
     */
    private function indexByName(): void
    {
        foreach ($this->processors as $processor) {
            $processorName = $processor->getName();

            if ($processorName === '') {
                throw new WiremockContextException('Processor name cannot be empty');
            }

            if (!$this->isValidProcessorName($processorName)) {
                throw new WiremockContextException(
                    sprintf(
                        'Invalid processor name "%s". Processor names must start with a letter, contain only lowercase letters, numbers, underscores, and dots, and end with a letter or number.',
                        $processorName
                    )
                );
            }

            if (isset($this->indexedProcessors[$processorName])) {
                throw new WiremockContextException(
                    sprintf('Duplicate processor name "%s" detected', $processorName)
                );
            }

            $this->indexedProcessors[$processorName] = $processor;
        }
    }

    private function isValidProcessorName(string $name): bool
    {
        return preg_match('/^[a-z]([a-z0-9_.]*[a-z0-9])?$/', $name) === 1;
    }

    /**
     * @throws WiremockContextException
     */
    public function getProcessor(string $processorName): PlaceholderProcessorInterface
    {
        if (!isset($this->indexedProcessors[$processorName])) {
            throw new WiremockContextException(
                sprintf('Unsupported processor "%s"', $processorName)
            );
        }

        return $this->indexedProcessors[$processorName];
    }
}
