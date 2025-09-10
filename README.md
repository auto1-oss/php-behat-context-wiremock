# php-behat-context-wiremock

This package provides a seamless integration between Behat tests and Wiremock, offering a straightforward method for mocking HTTP requests. It acts as a conduit between Behat scenarios and a Wiremock instance, enabling the creation of HTTP request expectations and mock responses without sacrificing Wiremock's inherent flexibility.

## Configuration Example

Below is an example of how to configure the Wiremock context within your Behat setup:

```yaml
- Auto1\BehatContext\Wiremock\WiremockContext:
      baseUrl: 'http://wiremock'
      stubsDirectory: '%paths.base%/features/stubs'
      cleanWiremockBeforeEachScenario: false # Optional, defaults to false
      allStubsMatchedAfterEachScenario: false # Optional, defaults to false
      stubsDirectoryIsFeatureDirectory: false # Optional, defaults to false
```

- `baseUrl`: The URL of your Wiremock instance.
- `stubsDirectory`: The base directory for Wiremock stubs. This cannot be specified if `stubsDirectoryIsFeatureDirectory` is enabled.
- `cleanWiremockBeforeEachScenario`: If true, Wiremock will be cleared before each scenario.
- `allStubsMatchedAfterEachScenario`: If true, ensures that all stubs are matched after each scenario.
- `stubsDirectoryIsFeatureDirectory`: If true, stubs will be sourced from the directory of a Behat feature file. This can only be enabled if `stubsDirectory` is not specified.
- Note: `stubsDirectory` and `stubsDirectoryIsFeatureDirectory` cannot be used simultaneously.

## Docker Integration

For those running Behat within Docker, integrating a Wiremock container is straightforward. The following configuration ensures that your tests wait for Wiremock to be fully initialized before running:

```yaml
  php-fpm:
    depends_on:
      wiremock:
        condition: service_healthy

  wiremock:
    image: wiremock/wiremock:latest
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:80/__admin/mappings"]
      interval: 10s
      timeout: 10s
      retries: 5
```

## Context Steps

### Defining Wiremock Stubs

- **Given wiremock stub**: This step allows you to define a Wiremock stub directly within your scenario.

  **Example**:
    ```gherkin
        Given wiremock stub:
        """
          {
            "request": {
              "method": "GET",
              "url": "/some/thing"
            },

            "response": {
              "status": 200,
              "body": "Hello, world!",
              "headers": {
                  "Content-Type": "text/plain"
              }
            }
          }
        ```

- **Given wiremock stubs from {file}**: This step loads stubs from a specified file or directory and sends them to Wiremock.

  **Example**:
    ```gherkin
        Given wiremock stubs from "dir/awesome-stub.json"
        And wiremock stubs from "dir2"
    ```

- **Given wiremock stubs from {file} should be called {count} times**: This step loads stubs from a specified file or directory, sends them to WireMock and also allows you to verify that the stub is called the specified number of times.

  **Example**:
    ```gherkin
        Given wiremock stubs from "dir/awesome-stub.json" should be called 2 times
        And wiremock stubs from "dir2" should be called 2 times
    ```

- **Given wiremock stubs from {file} should be called once**: This step loads stubs from a specified file or directory, sends them to WireMock and also allows you to verify that the stub is called once.

  **Example**:
    ```gherkin
        Given wiremock stubs from "dir/awesome-stub.json" should be called once
        And wiremock stubs from "dir2" should be called once
    ```

- **Given wiremock stubs from {file} should be called at least {count} times**: This step loads stubs from a specified file or directory, sends them to WireMock and also allows you to verify that the stub is called at least the specified number of times.

  **Example**:
    ```gherkin
        Given wiremock stubs from "dir/awesome-stub.json" should be called at least 2 times
        And wiremock stubs from "dir2" should be called at least 2 times
    ```
- **Given wiremock stubs from {file} should be called at most {count} times**: This step loads stubs from a specified file or directory, sends them to WireMock and also allows you to verify that the stub is not called more than the specified number of times.

  **Example**:
    ```gherkin
        Given wiremock stubs from "dir/awesome-stub.json" and should be called at most 2 times
        And wiremock stubs from "dir2" and should be called at most 2 times
    ```

### Managing Wiremock State

- **Given clean wiremock**: Resets Wiremock to its initial state.

  **Example**:
    ```gherkin
        Given clean wiremock
    ```

### Validating Stubs

- **Then all stubs should be matched**: Ensures that all added stubs were matched at least once and fails if there were any unexpected (unmatched) calls to Wiremock.

  **Example**:
    ```gherkin
        Then all stubs should be matched
    ```

## Placeholder Processors

The package includes a powerful placeholder processing system that allows you to dynamically transform content when loading Wiremock stubs. This feature enables you to process data (from external files or generated dynamically) and inject the transformed content directly into your stub definitions.

### How Placeholder Processors Work

Placeholder processors use a special syntax within your stub files to reference external content and apply transformations:

```
%processor_name(arguments)%
```

- `processor_name`: The name of the processor to use
- `arguments`: Comma-separated arguments passed to the processor

The processor system automatically:
1. Parses the placeholder syntax from your stub files
2. Loads the referenced external files (if applicable)
3. Applies the specified transformation
4. Replaces the placeholder with the processed content

It's important to note that processors are not limited to just retrieving content from files. They can perform any type of manipulation and generate any content that will be injected into the stub. While the built-in processors work with files, custom processors can generate content dynamically without necessarily relying on external files as input.

The system also provides error handling for general processor operations:
- **Processor Not Found**: Throws `WiremockContextException` when referencing non-existent processors

### Configuration

To use placeholder processors, you need to configure them in your Behat context. WiremockContext expects to receive an array of processor instances:

```yaml
- Auto1\BehatContext\Wiremock\WiremockContext:
      baseUrl: 'http://wiremock'
      stubsDirectory: '%paths.base%/features/stubs'
      placeholderProcessors:
        - '@Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\FlattenTextProcessor'
        - '@Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\JsonToUrlEncodedQueryStringProcessor'
```

Note that the `@` symbol indicates that these are service references. WiremockContext will receive the actual processor instances from your dependency injection container.

### Built-in Processors

#### FlattenTextProcessor

**Name**: `flatten_text`

**Purpose**: Flattens text by replacing all whitespace characters (spaces, tabs, newlines, etc.) with single spaces and trims leading/trailing whitespace.

**Syntax**: `%flatten_text(filename)%`

**Arguments**:
- `filename`: Path to the text file relative to the stubs directory

**Example**:

Given a file `data/multiline.txt`:
```
Hello
    World
        Test
```

And a stub file:
```json
{
  "request": {
    "method": "POST",
    "url": "/api/data",
    "bodyPatterns": [
      {
        "equalTo": "%flatten_text('data/multiline.txt')%"
      }
    ]
  },
  "response": {
    "status": 200
  }
}
```

The placeholder will be replaced with: `"Hello World Test"`

**Error Handling**:
- **File Not Found**: Throws `WiremockContextException` when referenced text files don't exist
- **Invalid Arguments**: Throws `WiremockContextException` for incorrect argument types

#### JsonToUrlEncodedQueryStringProcessor

**Name**: `json_to_url_encoded_query_string`

**Purpose**: Converts JSON data to URL-encoded query string format, with support for ignoring specific characters from encoding.

**Syntax**: `%json_to_url_encoded_query_string(filename, [ignored_characters])%`

**Arguments**:
- `filename`: Path to the JSON file relative to the stubs directory
- `ignored_characters`: Array of characters that should not be URL-encoded (optional). If not provided, an empty array will be used by default.

**Example 1 - Basic Usage**:

Given a file `data/params.json`:
```json
{
  "name": "John Doe",
  "age": 30,
  "active": true
}
```

And a stub file:
```json
{
  "request": {
    "method": "POST",
    "url": "/api/submit",
    "bodyPatterns": [
      {
        "equalTo": "%json_to_url_encoded_query_string('data/params.json')%"
      }
    ]
  },
  "response": {
    "status": 200
  }
}
```

The placeholder will be replaced with: `"name=John%20Doe&age=30&active=1"`

**Example 2 - With Ignored Characters**:

Given a file `data/url_params.json`:
```json
{
  "callback_url": "https://example.com/callback?token=abc123",
  "email": "user@example.com"
}
```

And a stub file:
```json
{
  "request": {
    "method": "POST",
    "url": "/api/webhook",
    "bodyPatterns": [
      {
        "equalTo": "%json_to_url_encoded_query_string('data/url_params.json', [':', '/', '?', '=', '@', '.'])%"
      }
    ]
  },
  "response": {
    "status": 200
  }
}
```

The placeholder will be replaced with: `"callback_url=https://example.com/callback?token=abc123&email=user@example.com"`

**Example 3 - Complex JSON with Nested Objects**:

Given a file `data/complex.json`:
```json
{
  "user": {
    "name": "John",
    "preferences": ["email", "sms"]
  },
  "metadata": {
    "source": "api",
    "version": 2
  }
}
```

The processor will JSON-encode nested objects and arrays:
- Result: `"user=%7B%22name%22%3A%22John%22%2C%22preferences%22%3A%5B%22email%22%2C%22sms%22%5D%7D&metadata=%7B%22source%22%3A%22api%22%2C%22version%22%3A2%7D"`

**Error Handling**:
- **File Not Found**: Throws `WiremockContextException` when referenced JSON files don't exist
- **Invalid JSON**: Throws `WiremockContextException` for malformed JSON content

### Advanced Argument Parsing

The placeholder parser supports sophisticated argument parsing including:

**Arrays**: Use square brackets to define arrays
```
%processor_name('file.txt', ['@', '.', ':'])%
```

**Associative Arrays**: Use `=>` syntax for key-value pairs
```
%processor_name('file.txt', ['key1' => 'value1', 'key2' => 'value2'])%
```

**Mixed Data Types**: Support for strings, integers, floats, booleans, and null
```
%processor_name('file.txt', [42, 3.14, true, false, null, 'string'])%
```

**Nested Arrays**: Support for multi-dimensional arrays
```
%processor_name('file.txt', [['nested', 'array'], ['another', 'nested']])%
```

### Creating Custom Processors

You can create custom processors by implementing the `PlaceholderProcessorInterface`:

```php
<?php

namespace Your\Namespace;

use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\PlaceholderProcessorInterface;

class CustomProcessor implements PlaceholderProcessorInterface
{
    public function getName(): string
    {
        return 'custom_processor';
    }

    public function process(string $stubsDirectory, array $args): string
    {
        // $stubsDirectory is the base directory for stubs

        // $args contains the arguments passed in the placeholder
        // For example, for %custom_processor('arg1', 'arg2')%
        // $args[0] would be 'arg1' and $args[1] would be 'arg2'

        // Your custom processing logic here
        // This can include any type of manipulation, not just file processing

        return $processedContent; // Return the content to be injected into the stub
    }
}
```

For file-based processors, you can extend `AbstractFileBasedPlaceholderProcessor`:

```php
<?php

namespace Your\Namespace;

use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\AbstractFileBasedPlaceholderProcessor;

class CustomFileProcessor extends AbstractFileBasedPlaceholderProcessor
{
    public function getName(): string
    {
        return 'custom_file_processor';
    }

    protected function processFileContent(string $fileContent, array $args): string
    {
        // $fileContent contains the content of the file
        // $args contains the arguments passed in the placeholder (excluding the filename)

        // Transform the file content
        return $transformedContent;
    }
}
```

### Processor Naming Rules

Processor names must follow these rules:
- Start with a letter (a-z)
- Contain only lowercase letters, numbers, underscores, and dots
- End with a letter or number
- Cannot be empty
- Must be unique within the registry

Valid examples: `flatten_text`, `json_to_url`, `custom_processor_v2`, `data.transformer`

This integration aims to simplify the process of testing HTTP interactions within your Behat scenarios, leveraging Wiremock's powerful mocking capabilities to enhance your testing suite.
