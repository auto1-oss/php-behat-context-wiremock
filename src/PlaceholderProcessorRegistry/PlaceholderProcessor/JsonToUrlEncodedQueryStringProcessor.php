<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;

class JsonToUrlEncodedQueryStringProcessor extends AbstractFileBasedPlaceholderProcessor
{
    private const NAME = 'json_to_url_encoded_query_string';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @throws WiremockContextException
     */
    protected function processFileContent(string $fileContent, array $args): string
    {
        $ignoredCharacters = $args[1] ?? [];

        if (!is_array($ignoredCharacters)) {
            throw new WiremockContextException('Ignored characters must be an array');
        }

        $jsonData = json_decode(trim($fileContent), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WiremockContextException(
                sprintf('Invalid JSON in file: %s, error: %s', $args[0], json_last_error_msg())
            );
        }

        foreach ($jsonData as $key => $value) {
            if (is_float($value) || is_array($value) || is_object($value)) {
                $jsonData[$key] = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            }
        }

        $queryString = http_build_query($jsonData, '', '&', PHP_QUERY_RFC3986);

        foreach ($ignoredCharacters as $ignoredCharacter) {
            $encoded = rawurlencode($ignoredCharacter);
            $queryString = str_replace($encoded, $ignoredCharacter, $queryString);
        }

        return $queryString;
    }
}
