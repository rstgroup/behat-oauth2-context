<?php
/**
 * @licence http://opensource.org/licenses/MIT MIT
 */

namespace RstGroup\Behat\OAuth2\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Context\SnippetAcceptingContext;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;

/**
 * OAuth2 context for Behat BDD tool.
 */
class OAuth2Context implements SnippetAcceptingContext
{
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
        $this->client = new GuzzleHttpClient();

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
        $this->setHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ]);

        $this->requestBody['client_id'] = $this->parameters['oauth2']['client_id'];
        $this->requestBody['client_secret'] = $this->parameters['oauth2']['client_secret'];
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
        $data = json_decode($response->getBody(true), true);

        if (!isset($data['refresh_token'])) {
            throw new \Exception(sprintf("Error refresh token. Response: %s", $response->getBody(true)));
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
     * @When I add resource owner credentials
     */
    public function iAddResourceOwnerCredentials()
    {
        $this->requestBody['username'] = $this->parameters['oauth2']['username'];
        $this->requestBody['password'] = $this->parameters['oauth2']['password'];
    }

    /**
     * @When I send a access token request
     */
    public function iMakeAAccessTokenRequest()
    {
        $url = $this->parameters['token_url'];
        $this->response = $this->getPostResponseFromUrl($url, $this->requestBody);

        $contentType = (string) $this->response->getHeader('Content-type');

        if ($contentType !== 'application/json') {
            throw new \Exception(sprintf("Content-type must be application/json %s", $this->echoLastResponse()));
        }
        $this->data = json_decode($this->response->getBody(true));
        $this->lastErrorJson = json_last_error();

        if ($this->lastErrorJson != JSON_ERROR_NONE) {
            throw new \Exception(sprintf("Error parsing response JSON " . "\n\n %s", $this->echoLastResponse()));
        }
    }

    /**
     * @When I make a access token request with given refresh token
     */
    public function iMakeAAccessTokenRequestWithGivenRefreshToken()
    {
        $this->requestBody['refresh_token'] = $this->refreshToken;
        $this->iMakeAAccessTokenRequest();
    }

    /**
     * @Then the response status code is :httpStatus
     */
    public function theResponseStatusCodeIs($httpStatus)
    {
        if ((string) $this->response->getStatusCode() !== $httpStatus) {
            throw new \Exception(sprintf("HTTP code does not match %s (actual: %s)\n\n %s", $httpStatus, $this->response->getStatusCode(), $this->echoLastResponse()));
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
        } catch (\LogicException $e) {
            throw new \Exception(sprintf("Property %s is not set!\n\n %s", $propertyName, $this->echoLastResponse()));
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
                throw new \Exception(sprintf("Property %s is not of the correct type: %s!\n\n %s", $propertyName, $typeString, $this->echoLastResponse()));
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
        throw new \Exception(sprintf("Given %s value is not %s\n\n %s", $propertyName, $propertyValue, $this->echoLastResponse()));
    }

    /**
     * @Then echo last response
     */
    public function echoLastResponse()
    {
        $this->printDebug(sprintf("Request:\n %s Response:\n %s", $this->request, $this->response));
    }

    /**
     * @Then the response is oauth2 format
     */
    public function theResponseHasTheOAuth2Format()
    {
        $expectedHeaders = [
            'cache-control' => 'no-store',
            'pragma' => 'no-cache'
        ];

        foreach ($expectedHeaders as $name => $value) {
            if ($this->response->getHeader($name) != $value) {
                throw new \Exception(sprintf("Header %s is should be %s, %s given", $name, $value, $this->response->getHeader($name)));
            }
        }
    }

    /**
     * Get POST response from URL without ssl verify and exception propagation
     *
     * @param string $url POST URL
     * @param array $body body array
     */
    protected function getPostResponseFromUrl($url, $body)
    {
        return $this->client->post($url, ['body' => $body, 'verify' => false, 'exceptions' => false]);
    }

    /**
     * Get property value from response data
     *
     * @param string $propertyName property name
     */
    protected function getPropertyValue($propertyName)
    {
        return $this->getValue($propertyName, $this->data);
    }

    /**
     * Get property value from data
     *
     * @param string $propertyName property name
     * @param mixed $data data as array or object
     */
    protected function getValue($propertyName, $data)
    {
        if (empty($data)) {
            throw new \Exception(sprintf("Response was not set %s", var_export($data, true)));
        }
        if (is_array($data) && array_key_exists($propertyName, $data)) {
            $data = $data[$propertyName];
            return $data;
        }
        if (is_object($data) && property_exists($data, $propertyName)) {
            $data = $data->$propertyName;
            return $data;
        }
        throw new \LogicException(sprintf("'%s' is not set", $propertyName));
    }

    /**
     * Set request Header by headers name and value
     *
     * @param string $name header name
     * @param string $value value for header name
     */
    protected function setHeader($name, $value)
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

    /**
     * Prints beautified debug string.
     *
     * @param string $string debug string
     */
    public function printDebug($string)
    {
        echo sprintf("\n\033[36m| %s\033[0m\n\n", strtr($string, ["\n" => "\n|  "]));
    }
}
