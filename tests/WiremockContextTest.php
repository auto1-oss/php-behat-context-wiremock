<?php

namespace Auto1\BehatContext\Tests;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
use Auto1\BehatContext\Wiremock\WiremockContext;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WiremockContextTest extends TestCase
{
    private $httpClient;
    private $wiremockContext;

    protected function setUp(): void
    {
        $this->httpClient      = $this->createMock(HttpClientInterface::class);
        $this->wiremockContext = new WiremockContext(
            'http://wiremock:8080',
            $this->httpClient
        );
    }

    public function testAddStub(): void
    {
        $dummyResponse = $this->createMock(ResponseInterface::class);
        $dummyResponse->expects($this->once())->method('toArray')->willReturn(['id' => 'dummy-id']);
        $this->httpClient->method('request')->willReturn($dummyResponse);
        $stubBody = '{"request": {"method": "GET", "url": "/some/path"}, "response": {"status": 200, "body": "OK"}}';
        $this->wiremockContext->addStub($stubBody);
    }

    public function testAddWiremockStubStep(): void
    {
        $dummyResponse = $this->createMock(ResponseInterface::class);
        $dummyResponse->expects($this->once())->method('toArray')->willReturn(['id' => 'dummy-id']);
        $this->httpClient->method('request')->willReturn($dummyResponse);
        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addWiremockStubStep(new \Behat\Gherkin\Node\PyStringNode([$stubBody], 0));
    }

    public function testAllStubsMatchedStepNoRequestsNoStubs(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('toArray')->willReturn([
            'requests' => [],
        ]);
        $this->httpClient->method('request')->willReturn($response);
        $this->wiremockContext->allStubsMatchedStep();
    }

    public function testAddStubWithHttpClientException(): void
    {
        $this->httpClient->method('request')->willThrowException(new \Exception('HTTP client error'));
        $this->expectException(WiremockContextException::class);
        $stubBody = '{"request": {"method": "GET", "url": "/error"}, "response": {"status": 500, "body": "Error"}}';
        $this->wiremockContext->addStub($stubBody);
    }

    public function testAllStubsMatchedStepAfterAddingStub(): void
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(
            ['id' => 'dummy-stub-id', 'request' => ['urlPath' => '/test']]
        );
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->expects($this->once())->method('toArray')->willReturn([
            'requests' => [
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
            ],
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url, $options) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );
        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody);
        $this->wiremockContext->allStubsMatchedStep();
    }

    public function testAllStubsMatchedStepUnexpectedCall(): void
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(
            ['id' => 'dummy-stub-id', 'request' => ['urlPath' => '/test']]
        );
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->expects($this->once())->method('toArray')->willReturn([
            'requests' => [
                [
                    'stubMapping' => [
                        'id' => 'another-dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
            ],
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url, $options) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );
        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody);
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Unexpected stub found: GET http://wiremock:8080/test');
        $this->wiremockContext->allStubsMatchedStep();
    }

    public function testAllStubsMatchedStepUnrequestedStub(): void
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(['id' => 'dummy-stub-id']);
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->expects($this->once())->method('toArray')->willReturn([
            'requests' => [
            ],
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url, $options) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );
        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody);
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage("Unrequested stub(s) found: [\n    {\n        \"id\": \"dummy-stub-id\"\n    }\n]");
        $this->wiremockContext->allStubsMatchedStep();
    }

    public function testAllStubsMatchedStepWithExactMatchCountStrategyFailed()
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(
            ['id' => 'dummy-stub-id', 'request' => ['urlPath' => '/test']]
        );
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->method('toArray')->willReturn([
            'requests' => [
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
            ]
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );

        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody, 1, 'exact');
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Stub with URL "/test" was expected to be called exactly 1 time(s), but was called 2 time(s)');
        $this->wiremockContext->allStubsMatchedAsExpectedForEachScenario();
    }

    public function testAllStubsMatchedStepWithExactMatchCountStrategy()
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(
            ['id' => 'dummy-stub-id', 'request' => ['urlPath' => '/test']]
        );
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->expects($this->once())->method('toArray')->willReturn([
            'requests' => [
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
            ]
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );

        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody, 1, 'exact');
        $this->wiremockContext->allStubsMatchedAsExpectedForEachScenario();
    }

    public function testAllStubsMatchedStepWithMaxMatchCountStrategyFailed()
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(
            ['id' => 'dummy-stub-id', 'request' => ['urlPath' => '/test']]
        );
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->method('toArray')->willReturn([
            'requests' => [
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
            ]
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );

        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody, 1, 'max');
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Stub with URL "/test" was expected to be called at most 1 time(s), but was called 2 time(s)');
        $this->wiremockContext->allStubsMatchedAsExpectedForEachScenario();
    }

    public function testAllStubsMatchedStepWithMinMatchCountStrategyFailed()
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(
            ['id' => 'dummy-stub-id', 'request' => ['urlPath' => '/test']]
        );
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->method('toArray')->willReturn([
            'requests' => [
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
            ]
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );

        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody, 3, 'min');
        $this->expectException(WiremockContextException::class);
        $this->expectExceptionMessage('Stub with URL "/test" was expected to be called minimum 3 time(s), but was called 2 time(s)');
        $this->wiremockContext->allStubsMatchedAsExpectedForEachScenario();
    }

    public function testAllStubsMatchedStepWithMaxMatchCountStrategy()
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(
            ['id' => 'dummy-stub-id', 'request' => ['urlPath' => '/test']]
        );
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->expects($this->once())->method('toArray')->willReturn([
            'requests' => [
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
            ]
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );

        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody, 1, 'max');
        $this->wiremockContext->allStubsMatchedAsExpectedForEachScenario();
    }

    public function testAllStubsMatchedStepWithMinMatchCountStrategy()
    {
        $addStubResponse = $this->createMock(ResponseInterface::class);
        $addStubResponse->expects($this->once())->method('toArray')->willReturn(
            ['id' => 'dummy-stub-id', 'request' => ['urlPath' => '/test']]
        );
        $matchedStubsResponse = $this->createMock(ResponseInterface::class);
        $matchedStubsResponse->expects($this->once())->method('toArray')->willReturn([
            'requests' => [
                [
                    'stubMapping' => [
                        'id' => 'dummy-stub-id',
                    ],
                    'request'     => [
                        'method'      => 'GET',
                        'absoluteUrl' => 'http://wiremock:8080/test',
                    ],
                ],
            ]
        ]);
        $this->httpClient->method('request')->willReturnCallback(
            function ($method, $url) use ($addStubResponse, $matchedStubsResponse) {
                if ($method === 'POST' && $url === 'http://wiremock:8080/__admin/mappings') {
                    return $addStubResponse;
                } elseif ($method === 'GET' && $url === 'http://wiremock:8080/__admin/requests') {
                    return $matchedStubsResponse;
                }

                return null;
            }
        );

        $stubBody = '{"request": {"method": "GET", "url": "/test"}, "response": {"status": 200, "body": "Success"}}';
        $this->wiremockContext->addStub($stubBody, 1, 'min');
        $this->wiremockContext->allStubsMatchedAsExpectedForEachScenario();
    }
}
