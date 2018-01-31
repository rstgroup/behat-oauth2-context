<?php

declare(strict_types=1);

namespace RstGroup\Behat\OAuth2\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;
use Exception;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use LogicException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Request\Serializer as RequestSerializer;
use Zend\Diactoros\Response\Serializer as ResponseSerializer;
use function GuzzleHttp\json_decode;

/**
 * OAuth2 context for Behat BDD tool.
 *
 * @licence http://opensource.org/licenses/MIT MIT
 */
class OAuth2Context implements SnippetAcceptingContext
{
    const GUZZLE_PARAMETERS = 'guzzle_parameters';

    protected $headers = [];

    /**
     * @var GuzzleHttpClient
     */
    protected $client = null;

    /**
     * @var ResponseInterface
     */
    protected $response = null;

    /**
     * @var RequestInterface
     */
    protected $request = null;

    protected $requestBody = [];

    protected $queryString;

    protected $data = null;

    protected $parameters = [];

    /**
     * @var string
     */
    protected $refreshToken;

    /**
     * @var string
     */
    protected $lastErrorJson;

    /**
     * Initializes context.
     */
    public function __construct(array $parameters)
    {
        // Initialize your context here
        $this->parameters = $parameters;

        $guzzleParameters = $this->getGuzzleParameters();
        $this->client = new GuzzleHttpClient($guzzleParameters);

        $timezone = ini_get('date.timezone');

        if (empty($timezone)) {
            date_default_timezone_set('UTC');
        }
    }

    /**
     * @When I create oauth2 request
     */
    public function iCreateOAuth2Request()
    {
        $this->requestBody = [];
        $this->queryString = [];
        $this->setHeaders([
            'Accept' => 'application/json',
        ]);

        $this->requestBody['client_id'] = $this->parameters['oauth2']['client_id'];
        $this->requestBody['client_secret'] = $this->parameters['oauth2']['client_secret'];
        $this->queryString['client_id'] = $this->parameters['oauth2']['client_id'];
        $this->queryString['client_secret'] = $this->parameters['oauth2']['client_secret'];
    }

    /**
     * @Given that I have an refresh token
     */
    public function thatIHaveAnRefreshToken()
    {
        $parameters = $this->parameters['oauth2'];
        $parameters['grant_type'] = 'password';

        $url = $this->parameters['token_url'];
        $response = $this->getPostResponseFromUrl($url, $parameters);
        $data = json_decode((string) $response->getBody(), true);

        if (!isset($data['refresh_token'])) {
            throw new Exception(sprintf("Error refresh token. Response: %s", (string) $response->getBody()));
        }
        $this->refreshToken = $data['refresh_token'];
    }

    /**
     * @Given that I have an refresh token from GET request
     */
    public function thatIHaveAnRefreshTokenFromGetRequest()
    {
        $response = $this->getGetResponseFromUrl($this->parameters['token_url']);
        $data = json_decode((string) $response->getBody(), true);

        if (empty($data['refresh_token'])) {
            throw new Exception(sprintf("Error refresh token. Response: %s", (string) $response->getBody()));
        }

        $this->refreshToken = $data['refresh_token'];
    }

    /**
     * @When I add the request parameters:
     */
    public function iAddTheRequestParameters(TableNode $parameters)
    {
        if ($parameters !== null) {
            foreach ($parameters->getRowsHash() as $key => $row) {
                $this->requestBody[$key] = $row;
            }
        }
    }

    /**
     * @When I add the query parameters:
     */
    public function iAddTheQueryParameters(TableNode $parameters)
    {
        if (null === $parameters) {
            return;
        }

        $this->queryString = array_merge($this->queryString, $parameters->getRowsHash());
    }

    /**
     * @When I add resource owner credentials
     */
    public function iAddResourceOwnerCredentials()
    {
        $this->requestBody['username'] = $this->parameters['oauth2']['username'];
        $this->requestBody['password'] = $this->parameters['oauth2']['password'];
    }

    /**
     * @When I add resource owner parameters :resourceOwner
     */
    public function iAddResourceOwnerParameters(string $resourceOwner)
    {
        if (empty($this->parameters['oauth2']['resource_owners'][$resourceOwner])) {
            throw new \InvalidArgumentException(sprintf("Resource owner not defined: %s", $resourceOwner));
        }

        $resourceOwnerConfig = $this->parameters['oauth2']['resource_owners'][$resourceOwner];
        $this->queryString['grant_type'] = $resourceOwnerConfig['grant_type'];
        $this->queryString['redirect_uri'] = $resourceOwnerConfig['redirect_uri'];
    }

    /**
     * @When I add resource owner valid access token :resourceOwner
     */
    public function iAddResourceOwnerValidAccessToken(string $resourceOwner)
    {
        if (empty($this->parameters['oauth2']['resource_owners'][$resourceOwner]) ||
            empty($this->parameters['oauth2']['resource_owners'][$resourceOwner]['access_token'])
        ) {
            throw new \InvalidArgumentException(sprintf("Resource owner not defined: %s", $resourceOwner));
        }

        $this->queryString['access_token'] = $this->parameters['oauth2']['resource_owners'][$resourceOwner]['access_token'];
    }

    /**
     * @When I send a access token request :method
     */
    public function iMakeAAccessTokenRequest(string $method)
    {
        $url = $this->parameters['token_url'];

        try {
            $this->response = 'GET' === $method
                ? $this->getGetResponseFromUrl($url)
                : $this->getPostResponseFromUrl($url, $this->requestBody)
            ;
        } catch (ClientException $e) {
            $this->response = $e->getResponse();
        }

        $contentType = $this->response->getHeaderLine('Content-type');
        if ($contentType !== 'application/json') {
            throw new Exception(sprintf("Content-type must be application/json %s", $this->echoLastResponse()));
        }

        $this->data = json_decode((string) $this->response->getBody());
        $this->lastErrorJson = json_last_error();

        if ($this->lastErrorJson != JSON_ERROR_NONE) {
            throw new Exception(sprintf("Error parsing response JSON " . "\n\n %s", $this->echoLastResponse()));
        }
    }

    /**
     * @When I make a access token request with given refresh token: :method
     */
    public function iMakeAAccessTokenRequestWithGivenRefreshToken(string $method)
    {
        if ('GET' === $method) {
            $this->queryString['refresh_token'] = $this->refreshToken;
        } else {
            $this->requestBody['refresh_token'] = $this->refreshToken;
        }

        $this->iMakeAAccessTokenRequest($method);
    }

    /**
     * @Then the response status code is :httpStatus
     */
    public function theResponseStatusCodeIs($httpStatus)
    {
        if ((int) $this->response->getStatusCode() !== $httpStatus) {
            throw new Exception(sprintf("HTTP code does not match %s (actual: %s)\n\n %s", $httpStatus, $this->response->getStatusCode(), $this->echoLastResponse()));
        }
    }

    /**
     * @Then the response has a :propertyName property
     */
    public function theResponseHasAProperty($propertyName)
    {
        if ((isset($this->parameters['recommended'][$propertyName]) && !$this->parameters['recommended'][$propertyName])) {
            return;
        }
        if ((isset($this->parameters['optional'][$propertyName]) && !$this->parameters['optional'][$propertyName])) {
            return;
        }

        try {
            return $this->getPropertyValue($propertyName);
        } catch (LogicException $e) {
            throw new Exception(sprintf("Property %s is not set!\n\n %s", $propertyName, $this->echoLastResponse()));
        }
    }

    /**
     * @Then the response has a :propertyName property and its type is :typeString
     */
    public function theResponseHasAPropertyAndItsTypeIs($propertyName, $typeString)
    {
        $value = $this->theResponseHasAProperty($propertyName);

        // check our type
        switch (strtolower($typeString)) {
            case 'numeric':
                if (is_numeric($value)) {
                    break;
                }
            case 'array':
                if (is_array($value)) {
                    break;
                }
            case 'null':
                if ($value === NULL) {
                    break;
                }
            default:
                throw new Exception(sprintf("Property %s is not of the correct type: %s!\n\n %s", $propertyName, $typeString, $this->echoLastResponse()));
        }
    }

    /**
     * @Given the response has a :propertyName property and it is equals :propertyValue
     */
    public function theResponseHasAPropertyAndItIsEquals($propertyName, $propertyValue)
    {
        $value = $this->theResponseHasAProperty($propertyName);

        if ($value == $propertyValue) {
            return;
        }
        throw new Exception(sprintf("Given %s value is not %s\n\n %s", $propertyName, $propertyValue, $this->echoLastResponse()));
    }

    /**
     * @Then echo last response
     */
    public function echoLastResponse()
    {
        $request = RequestSerializer::toString($this->request);
        $response = ResponseSerializer::toString($this->response);

        $this->printDebug(sprintf("Request:\n %s \n\n Response:\n %s", $request, $response));
    }

    /**
     * @Then the response is oauth2 format
     */
    public function theResponseHasTheOAuth2Format()
    {
        $expectedHeaders = [
            'cache-control' => ['no-store', 'no-store, private'],
            'pragma' => ['no-cache']
        ];

        foreach ($expectedHeaders as $name => $values) {
            $responseHeaderValue = $this->response->getHeaderLine($name);
            if (!in_array($responseHeaderValue, $values)) {
                throw new Exception(sprintf("Header %s is should be %s, %s given", $name, $value, $responseHeaderValue));
            }
        }
    }

    protected function getPostResponseFromUrl(string $url, array $body): ResponseInterface
    {
        $bodyAsQuery = http_build_query($body);
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $this->request = new Request('POST', $url, $headers, $bodyAsQuery);

        return $this->client->send($this->request);
    }

    protected function getGetResponseFromUrl(string $url): ResponseInterface
    {
        $this->request = new Request('GET', $url);

        return $this->client->send($this->request, ['query' => $this->queryString]);
    }

    /**
     * Get property value from response data
     *
     * @param string $propertyName property name
     */
    protected function getPropertyValue(string $propertyName)
    {
        return $this->getValue($propertyName, $this->data);
    }

    /**
     * Get property value from data
     *
     * @param string $propertyName property name
     * @param mixed $data data as array or object
     */
    protected function getValue(string $propertyName, $data)
    {
        if (empty($data)) {
            throw new Exception(sprintf("Response was not set %s", var_export($data, true)));
        }
        if (is_array($data) && array_key_exists($propertyName, $data)) {
            $data = $data[$propertyName];
            return $data;
        }
        if (is_object($data) && property_exists($data, $propertyName)) {
            $data = $data->$propertyName;
            return $data;
        }
        throw new LogicException(sprintf("'%s' is not set", $propertyName));
    }

    /**
     * Set request Header by headers name and value
     *
     * @param string $name header name
     * @param string $value value for header name
     */
    protected function setHeader(string $name, string $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * Set request Headers by headers array
     *
     * @param array $headers headers array $name => $value
     */
    protected function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    protected function getGuzzleParameters(): array
    {
        return isset($this->parameters[self::GUZZLE_PARAMETERS]) && is_array($this->parameters[self::GUZZLE_PARAMETERS]) ? $this->parameters[self::GUZZLE_PARAMETERS] : [];
    }

    /**
     * Prints beautified debug string.
     *
     * @param string $string debug string
     */
    public function printDebug(string $string)
    {
        echo sprintf("\n\033[36m| %s\033[0m\n\n", strtr($string, ["\n" => "\n|  "]));
    }
}
