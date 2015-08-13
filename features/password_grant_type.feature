@password_grant_type
Feature: OAuth2 Token Grant Password

  Scenario: Without user credentials
    When I create oauth2 request
    And I add the request parameters:
        | grant_type | password |
    And I send a access token request
    Then the response status code is 400
    And the response has a "error" property and it is equals "invalid_request"
    And the response has a "error_description" property

  Scenario: Invalid user credentials
    When I create oauth2 request
    And I add the request parameters:
        | grant_type | password |
        | username   | no       |
        | password   | bar      |
    And I send a access token request
    Then the response status code is 400
    And the response has a "error" property and it is equals "invalid_grant"
    And the response has a "error_description" property

  Scenario: Token Granted
    When I create oauth2 request
    And I add the request parameters:
        | grant_type | password   |
    And I add resource owner credentials
    And I send a access token request
    Then the response status code is 200
    And the response is oauth2 format
    And the response has a "access_token" property
    And the response has a "token_type" property and it is equals "Bearer"
    And the response has a "refresh_token" property
    And the response has a "expires_in" property and its type is numeric
    And the response has a "scope" property
