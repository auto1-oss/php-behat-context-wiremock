<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor;

interface PlaceholderProcessorInterface
{
    public function getName(): string;

    /**
     * @param mixed $args
     */
    public function process(string $stubsDirectory, array $args): string;
}
