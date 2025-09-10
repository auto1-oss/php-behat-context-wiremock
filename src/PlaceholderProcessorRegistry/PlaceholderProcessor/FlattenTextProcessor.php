<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor;

class FlattenTextProcessor extends AbstractFileBasedPlaceholderProcessor
{
    private const NAME = 'flatten_text';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function processFileContent(string $fileContent, array $args): string
    {
        return preg_replace('/\s+/', ' ', trim($fileContent));
    }
}
