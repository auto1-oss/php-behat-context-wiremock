<?php

/*
 * This file is part of the auto1-oss/php-behat-context-wiremock package.
 *
 * (c) AUTO1 Group GmbH https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Auto1\BehatContext\Wiremock;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
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

    public function __construct(
        private string $baseUrl,
        HttpClientInterface $client = null,
        private ?string $stubsDirectory = null,
        private bool $cleanWiremockBeforeEachScenario = false,
        private bool $allStubsMatchedAfterEachScenario = false,
        private bool $stubsDirectoryIsFeatureDirectory = false,
    ) {
        if ($stubsDirectoryIsFeatureDirectory === true && $stubsDirectory !== null) {
            throw new WiremockContextException('Only one of these arguments can be passed: stubsDirectory, stubsDirectoryIsFeatureDirectory');
        }

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
    #[Given('/^wiremock stubs from "([^"]+)" and should be called exactly (?P<expectedCallCount>\d+) times$/')]
    public function addWiremockStubFromFileShouldBeCalledExactlyStep(string $path, int $expectedCallCount): void
    {
        $this->addWiremockStubFromFile($path, $expectedCallCount, self::STUB_MATCH_COUNT_STRATEGY_EXACT);
    }

    /**
     * @throws WiremockContextException
     */
    #[Given('/^wiremock stubs from "([^"]+)" and should be called minimal (?P<expectedCallCount>\d+) times$/')]
    public function addWiremockStubFromFileShouldBeCalledMinimalStep(string $path, int $expectedCallCount): void
    {
        $this->addWiremockStubFromFile($path, $expectedCallCount, self::STUB_MATCH_COUNT_STRATEGY_MIN);
    }

    /**
     * @throws WiremockContextException
     */
    #[Given('/^wiremock stubs from "([^"]+)" and should be called at most (?P<expectedCallCount>\d+) times$/')]
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

    private function loadStubFromFile(string $filePath, ?int $expectedCallCount, string $type): void
    {
        $this->addStub(file_get_contents($filePath), $expectedCallCount, $type);
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

            $mappedRequestStubId =  $requestData['stubMapping']['id'];
            $requestedStubsIds[] = $mappedRequestStubId;
        }

        $requestedStubsIds = array_unique($requestedStubsIds);

        if ($diff = array_diff(array_keys($this->stubs), $requestedStubsIds)) {
            $unrequestedStubs = [];
            foreach ($diff as $stubId) {
                $unrequestedStubs[] = $this->stubs[$stubId]['response'];
            }

            throw new WiremockContextException('Unrequested stub(s) found: ' . json_encode($unrequestedStubs, JSON_PRETTY_PRINT));
        }
    }

    /**
     * @throws WiremockContextException
     */
    #[AfterScenario]
    public function allStubsMatchedAsExpectedForEachScenario(): void
    {
        $this->allStubsMatchedAsExpected();
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
    private function allStubsMatchedAsExpected(): void
    {
        $response = $this->sendRequest(
            'GET',
            self::PATH_REQUESTS
        );

        $requestedStubsCallCounts = [];

        foreach ($response['requests'] as $requestData) {
            $mappedRequestStubId =  $requestData['stubMapping']['id'];

            if (!isset($requestedStubsCallCounts[$mappedRequestStubId])) {
                $requestedStubsCallCounts[$mappedRequestStubId] = 0;
            }

            $requestedStubsCallCounts[$mappedRequestStubId]++;
        }

        $errors = [];

        foreach ($requestedStubsCallCounts as $stubId => $actualCount) {
            $expectedCount = $this->stubs[$stubId]['count'];
            $expectedType = $this->stubs[$stubId]['type'];

            $url = $this->stubs[$stubId]['response']['request']['urlPath'];

            switch ($expectedType) {
                case self::STUB_MATCH_COUNT_STRATEGY_EXACT:
                    if ($actualCount !== $expectedCount) {
                        $errors[] = sprintf(
                            'Stub with URL "%s" was expected to be called exactly %d time(s), but was called %d time(s)',
                            $url,
                            $expectedCount,
                            $actualCount
                        );
                    }
                    break;
                case self::STUB_MATCH_COUNT_STRATEGY_MAX:
                    if ($actualCount > $expectedCount) {
                        $errors[] = sprintf(
                            'Stub with URL "%s" was expected to be called at most %d time(s), but was called %d time(s)',
                            $url,
                            $expectedCount,
                            $actualCount
                        );
                    }
                    break;
                case self::STUB_MATCH_COUNT_STRATEGY_MIN:
                    if ($actualCount < $expectedCount) {
                        $errors[] = sprintf(
                            'Stub with URL "%s" was expected to be called minimum %d time(s), but was called %d time(s)',
                            $url,
                            $expectedCount,
                            $actualCount
                        );
                    }
                    break;
                case self::STUB_MATCH_COUNT_STRATEGY_ANY:
                    break;
                default:
                    throw new WiremockContextException(sprintf('Unknown expectation type %s for URL %s', $expectedType, $url));
            }

            if (!empty($errors)) {
                throw new WiremockContextException(implode("\n", $errors));
            }
        }
    }
}
