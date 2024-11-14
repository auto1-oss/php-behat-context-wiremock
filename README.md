# php-behat-context-wiremock

Elevate your testing game with this brilliant Behat context, perfect for crafting top-notch acceptance tests. Coupled with a WireMock server, it effortlessly mocks HTTP interactions for the service you're testing. This gem is a favourite among various teams at Auto1, celebrated for its versatility and lack of Auto1-specific quirks. It's a quintessential tool for those seeking a seamless and efficient testing experience, ensuring your projects run as smoothly as a well-brewed cup of tea. Cheers to better testing!

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

- **Given wiremock stubs from**: This step loads stubs from a specified file or directory and sends them to Wiremock.

  **Example**:
    ```gherkin
        Given wiremock stubs from "dir/awesome-stub.json"
        And wiremock stubs from "dir2"
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

This integration aims to simplify the process of testing HTTP interactions within your Behat scenarios, leveraging Wiremock's powerful mocking capabilities to enhance your testing suite.
