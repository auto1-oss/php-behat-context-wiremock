<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;

abstract class AbstractFileBasedPlaceholderProcessor implements PlaceholderProcessorInterface
{
    /**
     * Process the file content with processor-specific logic
     *
     * @param string $fileContent The content of the file
     * @param array $args The original arguments passed to process()
     * @return string The processed content
     * @throws WiremockContextException
     */
    abstract protected function processFileContent(string $fileContent, array $args): string;

    /**
     * @throws WiremockContextException
     */
    public function process(string $stubsDirectory, array $args): string
    {
        $filename = $args[0];
        $absolutePath = $stubsDirectory . '/' . $filename;

        $fileContent = $this->readFileContent($absolutePath);

        return $this->processFileContent($fileContent, $args);
    }

    /**
     * @throws WiremockContextException
     */
    protected function readFileContent(string $absolutePath): string
    {
        if (!file_exists($absolutePath)) {
            throw new WiremockContextException(
                sprintf('File not found: %s', $absolutePath)
            );
        }

        $fileContent = file_get_contents($absolutePath);
        if ($fileContent === false) {
            throw new WiremockContextException(
                sprintf('Unable to read file: %s', $absolutePath)
            );
        }

        return $fileContent;
    }
}
