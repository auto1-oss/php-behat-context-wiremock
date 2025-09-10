<?php

/*
 * This file is part of the auto1-oss/php-behat-context-wiremock package.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Auto1\BehatContext\Wiremock;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
use Auto1\BehatContext\Wiremock\PlaceholderParser\PlaceholderParser;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\PlaceholderProcessorInterface;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessorRegistry;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class WiremockContext implements Context
{
    private const PATH_MAPPINGS = '/__admin/mappings';
    private const PATH_REQUESTS = '/__admin/requests';
    private const PATH_RECORDINGS_START = '/__admin/recordings/start';
    private const PATH_RECORDINGS_STOP = '/__admin/recordings/stop';

    private const BODY_RECORDINGS_START = '{"targetBaseUrl":"%s","requestBodyPattern":{"matcher":"equalToJson","ignoreArrayOrder":true,"ignoreExtraElements":true},"persist":true}';

    private const STUB_MATCH_COUNT_STRATEGY_EXACT = 'exact';
    private const STUB_MATCH_COUNT_STRATEGY_MAX = 'max';
    private const STUB_MATCH_COUNT_STRATEGY_MIN = 'min';
    private const STUB_MATCH_COUNT_STRATEGY_ANY = 'any';

    private HttpClientInterface $client;

    /**
     * @var array<string, array>
     */
    private array $stubs = [];

    private PlaceholderProcessorRegistry $placeholderProcessorRegistry;

    private PlaceholderParser $placeholderParser;

    /**
     * @param PlaceholderProcessorInterface[] $placeholderProcessors
     * @throws WiremockContextException
     */
    public function __construct(
        private string $baseUrl,
        HttpClientInterface $client = null,
        private ?string $stubsDirectory = null,
        private array $placeholderProcessors = [],
        private bool $cleanWiremockBeforeEachScenario = false,
        private bool $allStubsMatchedAfterEachScenario = false,
        private bool $stubsDirectoryIsFeatureDirectory = false,
    ) {
        if ($stubsDirectoryIsFeatureDirectory === true && $stubsDirectory !== null) {
            throw new WiremockContextException('Only one of these arguments can be passed: stubsDirectory, stubsDirectoryIsFeatureDirectory');
        }

        $this->placeholderProcessorRegistry = new PlaceholderProcessorRegistry($this->placeholderProcessors);

        $this->placeholderParser = new PlaceholderParser();

        $this->client = $client ?: HttpClient::create();
    }

    #[Given('/^wiremock stub:$/')]
    public function addWiremockStubStep(PyStringNode $body): void
    {
        $this->addStub($body->getRaw());
    }

    /**
     * @throws WiremockContextException
     */
    #[Given('/^wiremock stubs from "([^"]+)"$/')]
    public function addWiremockStubFromFileStep(string $path): void
    {
        $this->addWiremockStubFromFile($path);
    }

    /**
     * @throws WiremockContextException
     */
    #[Given('/^wiremock stubs from "([^"]+)" should be called (?P<expectedCallCount>\d+) times$/')]
    public function addWiremockStubFromFileShouldBeCalledStep(string $path, int $expectedCallCount): void
    {
        $this->addWiremockStubFromFile($path, $expectedCallCount, self::STUB_MATCH_COUNT_STRATEGY_EXACT);
    }

    /**
     * @throws WiremockContextException
     */
    #[Given('/^wiremock stubs from "([^"]+)" should be called once$/')]
    public function addWiremockStubFromFileShouldBeCalledOnceStep(string $path): void
    {
        $this->addWiremockStubFromFile($path, 1, self::STUB_MATCH_COUNT_STRATEGY_EXACT);
    }

    /**
     * @throws WiremockContextException
     */
    #[Given('/^wiremock stubs from "([^"]+)" should be called at least (?P<expectedCallCount>\d+) times$/')]
    public function addWiremockStubFromFileShouldBeCalledAtLeastStep(string $path, int $expectedCallCount): void
    {
        $this->addWiremockStubFromFile($path, $expectedCallCount, self::STUB_MATCH_COUNT_STRATEGY_MIN);
    }

    /**
     * @throws WiremockContextException
     */
    #[Given('/^wiremock stubs from "([^"]+)" should be called at most (?P<expectedCallCount>\d+) times$/')]
    public function addWiremockStubFromFileShouldBeCalledAtMostStep(string $path, int $expectedCallCount): void
    {
        $this->addWiremockStubFromFile($path, $expectedCallCount, self::STUB_MATCH_COUNT_STRATEGY_MAX);
    }

    #[Given('/^clean wiremock$/')]
    public function cleanWiremockStep(): void
    {
        $this->cleanWiremock();
    }

    #[Then('/^all stubs should be matched$/')]
    public function allStubsMatchedStep(): void
    {
        $this->allStubsMatched();
    }

    #[Then('/^start wiremock recording with redirection to "([^"]+)"$/')]
    public function startRecordingStep(string $url): void
    {
        $this->sendRequest(
            'POST',
            self::PATH_RECORDINGS_START,
            sprintf(self::BODY_RECORDINGS_START, $url)
        );
    }

    #[Then('/^stop wiremock recording$/')]
    public function stopRecordingStep(): void
    {
        $this->sendRequest(
            'POST',
            self::PATH_RECORDINGS_STOP
        );
    }

    #[Then('/^stop wiremock recording and save mocks to "([^"]+)"$/')]
    public function stopRecordingAndSaveStep(string $path): void
    {
        $result = $this->sendRequest(
            'POST',
            self::PATH_RECORDINGS_STOP
        );

        $mappings = $result['mappings'];
        array_walk($mappings, function (array &$mapping) {
            $urlData = parse_url($mapping['request']['url']);

            unset($mapping['request']['url']);
            $mapping['request']['urlPath'] = $urlData['path'];

            $queryParams = [];
            parse_str($urlData['query'], $queryParams);
            unset($queryParams['wa_key']);

            $stubQueryParameters = [];
            foreach ($queryParams as $name => $value) {
                $stubQueryParameters[$name] = ['equalTo' => $value];
            }
            if ($stubQueryParameters) {
                $mapping['request']['queryParameters'] = $stubQueryParameters;
            }

            if (isset($mapping['response']['body'])) {
                $jsonBody = @json_decode($mapping['response']['body']);
                if ($jsonBody) {
                    $mapping['response']['jsonBody'] = $jsonBody;
                    unset($mapping['response']['body']);
                }
            }

            if (isset($mapping['request']['bodyPatterns'])) {
                foreach ($mapping['request']['bodyPatterns'] as &$bodyPattern) {
                    if (isset($bodyPattern['equalToJson'])) {
                        $bodyPattern['equalToJson'] = json_decode($bodyPattern['equalToJson']);
                    }
                }
            }

            unset($mapping['id']);
            unset($mapping['uuid']);
            unset($mapping['persistent']);
            unset($mapping['response']['headers']);
        });

        array_walk($mappings, function (array &$mapping, $key) use ($path) {
            $filename = sprintf("%02d_%s.json", $key, $mapping['name']);

            file_put_contents(
                join('/', [$absolutePath = $this->stubsDirectory, $path, $filename]),
                json_encode($mapping, JSON_PRETTY_PRINT)
            );
        });
    }

    #[AfterScenario]
    public function allStubsMatchedForEachScenario(): void
    {
        if ($this->allStubsMatchedAfterEachScenario) {
            $this->allStubsMatched();
        }
    }

    #[BeforeScenario]
    public function cleanWiremockForEachScenario(): void
    {
        if ($this->cleanWiremockBeforeEachScenario) {
            $this->cleanWiremock();
        }
    }

    #[BeforeScenario]
    public function setFeatureDirectory(BeforeScenarioScope $scope): void
    {
        if ($this->stubsDirectoryIsFeatureDirectory) {
            $featureFilePath = $scope->getFeature()->getFile();
            $this->stubsDirectory = dirname($featureFilePath);
        }
    }

    private function cleanWiremock(): void
    {
        $this->sendRequest(
            'DELETE',
            self::PATH_MAPPINGS
        );
        $this->sendRequest(
            'DELETE',
            self::PATH_REQUESTS
        );
    }

    /**
     * @throws WiremockContextException
     */
    private function sendRequest(string $method, string $url, ?string $body = null): array
    {
        $options = [];
        if ($body) {
            $options['body'] = $body;
        }

        try {
            $response = $this->client->request($method, $this->baseUrl . $url, $options);
        } catch (Throwable $exception) {
            throw new WiremockContextException('Exception occurred during sending request', 0, $exception);
        }

        try {
            return $response->toArray();
        } catch (Throwable $exception) {
            throw new WiremockContextException('Exception occurred during deserialization process', 0, $exception);
        }
    }

    public function addStub(
        string $body,
        ?int $expectedCallCount = null,
        string $type = self::STUB_MATCH_COUNT_STRATEGY_ANY
    ): void {
        $response = $this->sendRequest(
            'POST',
            self::PATH_MAPPINGS,
            $body
        );

        $stubId = $response['id'];

        $this->stubs[$stubId]['response'] = $response;
        $this->stubs[$stubId]['count'] = $expectedCallCount;
        $this->stubs[$stubId]['type'] = $type;
    }

    /**
     * @throws WiremockContextException
     */
    private function loadStubFromFile(string $filePath, ?int $expectedCallCount, string $type): void
    {
        $body = file_get_contents($filePath);

        if (count($this->placeholderProcessors) > 0) {
            $body = $this->processPlaceholderValuesInjection($body);
        }

        $this->addStub($body, $expectedCallCount, $type);
    }

    private function processPlaceholderValuesInjection(string $rawBody): string
    {
        $pattern = '/%([a-zA-Z_][a-zA-Z0-9_]*)\(((?:[^%]|%(?![a-zA-Z_]))*?)\)%/';
        $matches = [];

        if (preg_match_all($pattern, $rawBody, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $placeToInject = $match[0];
                $processorName = $match[1];
                $arguments = $this->placeholderParser->parse($match[2]);

                $processor = $this->placeholderProcessorRegistry->getProcessor($processorName);
                $contentToInject = $processor->process($this->stubsDirectory, [...$arguments]);

                $rawBody = str_replace($placeToInject, $contentToInject, $rawBody);
            }
        }

        return $rawBody;
    }

    /**
     * @throws WiremockContextException
     */
    private function allStubsMatched(): void
    {
        $response = $this->sendRequest(
            'GET',
            self::PATH_REQUESTS
        );

        $requestedStubsIds = [];
        $requestedStubsCallCounts = [];

        foreach ($response['requests'] as $requestData) {
            if (!isset($requestData['stubMapping'])) {
                throw new WiremockContextException(sprintf(
                    'Unexpected request found: %s %s',
                    $requestData["request"]["method"],
                    $requestData["request"]["absoluteUrl"]
                ));
            }

            if (!array_key_exists($requestData['stubMapping']['id'], $this->stubs)) {
                throw new WiremockContextException(sprintf(
                    'Unexpected stub found: %s %s',
                    $requestData["request"]["method"],
                    $requestData["request"]["absoluteUrl"]
                ));
            }

            $mappedRequestStubId = $requestData['stubMapping']['id'];
            $requestedStubsIds[] = $mappedRequestStubId;

            if (!isset($requestedStubsCallCounts[$mappedRequestStubId])) {
                $requestedStubsCallCounts[$mappedRequestStubId] = [
                    'count' => 0,
                    'method' => $requestData["request"]["method"],
                    'absoluteUrl' => $requestData["request"]["absoluteUrl"],
                ];
            }

            $requestedStubsCallCounts[$mappedRequestStubId]['count']++;
        }

        $requestedStubsIds = array_unique($requestedStubsIds);

        if ($diff = array_diff(array_keys($this->stubs), $requestedStubsIds)) {
            $unrequestedStubs = [];
            foreach ($diff as $stubId) {
                $unrequestedStubs[] = $this->stubs[$stubId]['response'];
            }

            throw new WiremockContextException('Unrequested stub(s) found: ' . json_encode($unrequestedStubs, JSON_PRETTY_PRINT));
        }

        $this->checkRequestedStubsCallCounts($requestedStubsCallCounts);
    }

    /**
     * @throws WiremockContextException
     */
    private function addWiremockStubFromFile(
        string $path,
        ?int $expectedCallCount = null,
        string $type = self::STUB_MATCH_COUNT_STRATEGY_ANY
    ): void {
        $absolutePath = $this->stubsDirectory . '/' . $path;

        if (is_dir($absolutePath)) {
            $files = scandir($absolutePath);

            foreach ($files as $file) {
                $filePath = $absolutePath . '/' . $file;
                if (is_dir($filePath)) {
                    continue;
                }

                try {
                    $this->loadStubFromFile($filePath, $expectedCallCount, $type);
                } catch (Throwable $exception) {
                    throw new WiremockContextException(
                        sprintf(
                            'Unable to load file "%s"',
                            $filePath
                        )
                        , 0, $exception);
                }
            }
        } else {
            $this->loadStubFromFile($absolutePath, $expectedCallCount, $type);
        }
    }

    /**
     * @param array $requestedStubsCallCounts
     * @throws WiremockContextException
     */
    private function checkRequestedStubsCallCounts(array $requestedStubsCallCounts): void
    {
        $errors = [];

        foreach ($requestedStubsCallCounts as $stubId => $requestedStubCallCount) {
            $expectedCount = $this->stubs[$stubId]['count'];
            $expectedType = $this->stubs[$stubId]['type'];

            $actualCount = $requestedStubCallCount['count'];
            $absoluteUrl = $requestedStubCallCount['absoluteUrl'];

            $method = $requestedStubCallCount['method'];

            switch ($expectedType) {
                case self::STUB_MATCH_COUNT_STRATEGY_EXACT:
                    if ($actualCount !== $expectedCount) {
                        $errors[] = sprintf(
                            '%s "%s" was expected to be called %d time(s), but was called %d time(s)',
                            $method,
                            $absoluteUrl,
                            $expectedCount,
                            $actualCount
                        );
                    }
                    break;
                case self::STUB_MATCH_COUNT_STRATEGY_MAX:
                    if ($actualCount > $expectedCount) {
                        $errors[] = sprintf(
                            '%s "%s" was expected to be called at most %d time(s), but was called %d time(s)',
                            $method,
                            $absoluteUrl,
                            $expectedCount,
                            $actualCount
                        );
                    }
                    break;
                case self::STUB_MATCH_COUNT_STRATEGY_MIN:
                    if ($actualCount < $expectedCount) {
                        $errors[] = sprintf(
                            '%s "%s" was expected to be called at least %d time(s), but was called %d time(s)',
                            $method,
                            $absoluteUrl,
                            $expectedCount,
                            $actualCount
                        );
                    }
                    break;
                case self::STUB_MATCH_COUNT_STRATEGY_ANY:
                    break;
                default:
                    throw new WiremockContextException(
                        sprintf(
                            'Unknown expectation type %s for %s %s',
                            $method,
                            $expectedType,
                            $absoluteUrl
                        )
                    );
            }
        }

        if (!empty($errors)) {
            throw new WiremockContextException(implode("\n", $errors));
        }
    }
}
