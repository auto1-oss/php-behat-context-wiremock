<?php
/*
 * This file is part of the auto1-oss/php-behat-context-wiremock.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Auto1\BehatContext\Tests;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\FlattenTextProcessor;
use Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor\JsonToUrlEncodedQueryStringProcessor;
use Auto1\BehatContext\Wiremock\WiremockContext;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WiremockContextTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private WiremockContext $wiremockContext;
    private string $defaultStubsDir;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->defaultStubsDir = sys_get_temp_dir() . '/wiremock_stubs';
        if (!is_dir($this->defaultStubsDir)) {
            mkdir($this->defaultStubsDir, 0700, true);
        }

        $flattenTextProcessor = new FlattenTextProcessor();
        $jsonProcessor = new JsonToUrlEncodedQueryStringProcessor();

        $this->wiremockContext = new WiremockContext(
            'http://wiremock:8080',
            $this->httpClient,
            $this->defaultStubsDir,
            [$flattenTextProcessor, $jsonProcessor]
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
        $this->expectExceptionMessage('GET "http://wiremock:8080/test" was expected to be called 1 time(s), but was called 2 time(s)');
        $this->wiremockContext->allStubsMatchedStep();
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
        $this->wiremockContext->allStubsMatchedStep();
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
        $this->expectExceptionMessage('GET "http://wiremock:8080/test" was expected to be called at most 1 time(s), but was called 2 time(s)');
        $this->wiremockContext->allStubsMatchedStep();
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
        $this->expectExceptionMessage('GET "http://wiremock:8080/test" was expected to be called at least 3 time(s), but was called 2 time(s)');
        $this->wiremockContext->allStubsMatchedStep();
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
        $this->wiremockContext->allStubsMatchedStep();
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
        $this->wiremockContext->allStubsMatchedStep();
    }

    public function testAddWiremockStubFromFileStepWithPlaceholderProcessing(): void
    {
        try {
            $stubContent = json_encode([
                'request' => [
                    'method' => 'POST',
                    'urlPath' => '/api/test',
                    'bodyPatterns' => [
                        ['equalToJson' => '%flatten_text(test_data.json)%']
                    ]
                ],
                'response' => [
                    'status' => 200,
                    'jsonBody' => [
                        'message' => 'success',
                        'query' => '%json_to_url_encoded_query_string(query_data.json, [])%'
                    ]
                ]
            ], JSON_PRETTY_PRINT);

            file_put_contents($this->defaultStubsDir . '/test_stub.json', $stubContent);

            $testDataContent = "  This is   a test\n  with   multiple   spaces  \n  and newlines  ";
            file_put_contents($this->defaultStubsDir . '/test_data.json', $testDataContent);

            $queryData = json_encode(['param1' => 'value1', 'param2' => 'value2']);
            file_put_contents($this->defaultStubsDir . '/query_data.json', $queryData);

            $dummyResponse = $this->createMock(ResponseInterface::class);
            $dummyResponse->expects($this->once())->method('toArray')->willReturn(['id' => 'stub-123']);

            $this->httpClient->expects($this->once())
                ->method('request')
                ->with(
                    'POST',
                    'http://wiremock:8080/__admin/mappings',
                    $this->callback(function ($options) {
                        $body = json_decode($options['body'], true);

                        return $body['request']['bodyPatterns'][0]['equalToJson'] === 'This is a test with multiple spaces and newlines'
                            && $body['response']['jsonBody']['query'] === 'param1=value1&param2=value2';
                    })
                )
                ->willReturn($dummyResponse);

            $this->wiremockContext->addWiremockStubFromFileStep('test_stub.json');

        } finally {
            $filesToClean = ['test_stub.json', 'test_data.json', 'query_data.json'];
            foreach ($filesToClean as $file) {
                if (file_exists($this->defaultStubsDir . '/' . $file)) {
                    unlink($this->defaultStubsDir . '/' . $file);
                }
            }
        }
    }

    public function testAddWiremockStubFromFileStepWithMultiplePlaceholders(): void
    {
        try {
            $stubContent = json_encode([
                'request' => [
                    'method' => 'GET',
                    'urlPath' => '/api/data',
                    'queryParameters' => [
                        'filter' => ['equalTo' => '%flatten_text(filter.txt)%']
                    ]
                ],
                'response' => [
                    'status' => 200,
                    'body' => '%flatten_text(response.txt)%'
                ]
            ], JSON_PRETTY_PRINT);

            file_put_contents($this->defaultStubsDir . '/multi_placeholder_stub.json', $stubContent);

            file_put_contents($this->defaultStubsDir . '/filter.txt', "  category:electronics   AND   price:>100  ");
            file_put_contents($this->defaultStubsDir . '/response.txt', "  Success!   Data   retrieved   successfully.  ");

            $queryParamsData = json_encode(['search' => 'test', 'limit' => '10']);
            file_put_contents($this->defaultStubsDir . '/query_params.json', $queryParamsData);

            $dummyResponse = $this->createMock(ResponseInterface::class);
            $dummyResponse->expects($this->once())->method('toArray')->willReturn(['id' => 'stub-456']);

            $this->httpClient->expects($this->once())
                ->method('request')
                ->with(
                    'POST',
                    'http://wiremock:8080/__admin/mappings',
                    $this->callback(function ($options) {
                        $body = json_decode($options['body'], true);

                        return $body['request']['queryParameters']['filter']['equalTo'] === 'category:electronics AND price:>100'
                            && $body['response']['body'] === 'Success! Data retrieved successfully.';
                    })
                )
                ->willReturn($dummyResponse);

            $this->wiremockContext->addWiremockStubFromFileStep('multi_placeholder_stub.json');

        } finally {
            $filesToClean = ['multi_placeholder_stub.json', 'filter.txt', 'response.txt', 'query_params.json'];
            foreach ($filesToClean as $file) {
                if (file_exists($this->defaultStubsDir . '/' . $file)) {
                    unlink($this->defaultStubsDir . '/' . $file);
                }
            }
        }
    }

    public function testAddWiremockStubFromFileStepWithoutPlaceholders(): void
    {
        try {
            $stubContent = json_encode([
                'request' => [
                    'method' => 'GET',
                    'urlPath' => '/api/simple'
                ],
                'response' => [
                    'status' => 200,
                    'jsonBody' => [
                        'message' => 'No placeholders here'
                    ]
                ]
            ], JSON_PRETTY_PRINT);

            file_put_contents($this->defaultStubsDir . '/simple_stub.json', $stubContent);

            $dummyResponse = $this->createMock(ResponseInterface::class);
            $dummyResponse->expects($this->once())->method('toArray')->willReturn(['id' => 'stub-simple']);

            $this->httpClient->expects($this->once())
                ->method('request')
                ->with(
                    'POST',
                    'http://wiremock:8080/__admin/mappings',
                    $this->callback(function ($options) use ($stubContent) {
                        return $options['body'] === $stubContent;
                    })
                )
                ->willReturn($dummyResponse);

            $this->wiremockContext->addWiremockStubFromFileStep('simple_stub.json');

        } finally {
            if (file_exists($this->defaultStubsDir . '/simple_stub.json')) {
                unlink($this->defaultStubsDir . '/simple_stub.json');
            }
        }
    }
}
