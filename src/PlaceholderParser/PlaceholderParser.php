<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderParser;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;

class PlaceholderParser
{
    public function parse(string $argumentsString): array
    {
        $parsedArguments = $this->parseArguments($argumentsString);

        return array_map([$this, 'convertArgumentsToPHPTyped'], $parsedArguments);
    }

    private function parseArguments(string $argString): array
    {
        if (trim($argString) === '') {
            return [];
        }

        return $this->splitStringByDelimiter($argString, ',');
    }

    /**
     * @throws WiremockContextException
     */
    private function convertArgumentsToPHPTyped(string $arg): mixed
    {
        $arg = trim($arg);

        // Array
        if (str_starts_with($arg, '[') && str_ends_with($arg, ']')) {
            return $this->parseArrayArgument($arg);
        }

        return $this->convertScalarType($arg);
    }

    /**
     * Parse array argument supporting nested arrays and associative arrays
     * @throws WiremockContextException
     */
    private function parseArrayArgument(string $arrayString): array
    {
        $arrayString = trim($arrayString);

        // Remove outer brackets
        $content = substr($arrayString, 1, -1);
        $content = trim($content);

        if ($content === '') {
            return [];
        }

        $elements = $this->splitStringByDelimiter($content, ',');
        $result = [];

        foreach ($elements as $element) {
            $element = trim($element);

            if ($this->hasMalformedAssociativeSyntax($element)) {
                throw new WiremockContextException('Invalid associative array syntax: use "=>" instead of "="');
            }

            // Check if this is an associative array element (key => value)
            if ($this->containsPatternAtTopLevel($element, '=>')) {
                [$key, $value] = $this->parseAssociativeElement($element);
                $result[$key] = $value;
            } else {
                $result[] = $this->convertArgumentsToPHPTyped($element);
            }
        }

        return $result;
    }

    /**
     * Check if an element has malformed associative syntax (single '=' instead of '=>')
     */
    private function hasMalformedAssociativeSyntax(string $element): bool
    {
        $position = $this->findPatternAtTopLevel($element, '=', function($char, $nextChar) {
            return $nextChar !== '>'; // Single '=' not followed by '>'
        });

        if ($position === -1) {
            return false;
        }

        // Validate it's a malformed assignment (has content before and after)
        $beforeEquals = trim(substr($element, 0, $position));
        $afterEquals = trim(substr($element, $position + 1));

        return $beforeEquals !== '' && $afterEquals !== '';
    }

    /**
     * Parse associative element (key => value)
     * @throws WiremockContextException
     */
    private function parseAssociativeElement(string $element): array
    {
        $arrowPos = $this->findPatternAtTopLevel($element, '=>');

        if ($arrowPos === -1) {
            throw new WiremockContextException('Invalid associative array syntax');
        }

        $keyPart = trim(substr($element, 0, $arrowPos));
        $valuePart = trim(substr($element, $arrowPos + 2));

        $key = $this->convertScalarType($keyPart);
        $value = $this->convertArgumentsToPHPTyped($valuePart);

        if (!is_string($key) && !is_int($key)) {
            throw new WiremockContextException('Array keys must be strings or integers');
        }

        return [$key, $value];
    }

    private function convertScalarType(string $arg): int|float|string|null|bool
    {
        // Null
        if (strtolower($arg) === 'null') return null;

        // Boolean
        if (strtolower($arg) === 'true') return true;
        if (strtolower($arg) === 'false') return false;

        // Numeric
        if (is_numeric($arg)) return str_contains($arg, '.') ? (float)$arg : (int)$arg;

        // Quoted string
        if ((str_starts_with($arg, "'") && str_ends_with($arg, "'")) ||
            (str_starts_with($arg, '"') && str_ends_with($arg, '"'))) {
            return stripslashes(substr($arg, 1, -1));
        }

        // Fallback: return as string
        return $arg;
    }

    /**
     * Split string by delimiter, respecting quotes and brackets
     */
    private function splitStringByDelimiter(string $input, string $delimiter): array
    {
        $result = [];
        $current = '';
        $state = $this->createParsingState();

        $length = strlen($input);
        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            $prevChar = $i > 0 ? $input[$i - 1] : '';

            $this->updateParsingState($state, $char, $prevChar);

            if ($char === $delimiter && $this->isAtTopLevel($state)) {
                $result[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $result[] = trim($current);
        }

        return $result;
    }

    /**
     * Check if string contains pattern at top level (outside quotes/brackets)
     */
    private function containsPatternAtTopLevel(string $input, string $pattern): bool
    {
        return $this->findPatternAtTopLevel($input, $pattern) !== -1;
    }

    /**
     * Find position of pattern at top level, with optional condition callback
     */
    private function findPatternAtTopLevel(string $input, string $pattern, ?callable $condition = null): int
    {
        $patternLength = strlen($pattern);
        $state = $this->createParsingState();

        $length = strlen($input);
        for ($i = 0; $i <= $length - $patternLength; $i++) {
            $char = $input[$i];
            $prevChar = $i > 0 ? $input[$i - 1] : '';

            $this->updateParsingState($state, $char, $prevChar);

            if ($this->isAtTopLevel($state) && substr($input, $i, $patternLength) === $pattern) {
                // Apply condition if provided
                if ($condition === null) {
                    return $i;
                }

                $nextChar = $i + $patternLength < $length ? $input[$i + $patternLength] : '';
                if ($condition($char, $nextChar)) {
                    return $i;
                }
            }
        }

        return -1;
    }

    /**
     * Create initial parsing state
     */
    private function createParsingState(): array
    {
        return [
            'bracketDepth' => 0,
            'insideQuotes' => false,
            'quoteChar' => ''
        ];
    }

    /**
     * Update parsing state based on current character
     */
    private function updateParsingState(array &$state, string $char, string $prevChar): void
    {
        // Handle quotes
        if (($char === '"' || $char === "'") && $prevChar !== '\\') {
            if ($state['insideQuotes'] && $char === $state['quoteChar']) {
                // Closing quote
                $state['insideQuotes'] = false;
                $state['quoteChar'] = '';
            } elseif (!$state['insideQuotes']) {
                // Opening quote
                $state['insideQuotes'] = true;
                $state['quoteChar'] = $char;
            }
        }

        // Track bracket depth if outside quotes
        if (!$state['insideQuotes']) {
            if ($char === '[') {
                $state['bracketDepth']++;
            } elseif ($char === ']') {
                $state['bracketDepth']--;
            }
        }
    }

    /**
     * Check if we're at top level (outside quotes and brackets)
     */
    private function isAtTopLevel(array $state): bool
    {
        return !$state['insideQuotes'] && $state['bracketDepth'] === 0;
    }
}
