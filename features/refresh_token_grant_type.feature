@refresh_token_grant_type
Feature: Refresh Token Grant Type

  Scenario: Without Refresh Token
    When I create oauth2 request
    And I add the request parameters:
        | grant_type | refresh_token   |
    And I send a access token request: "POST"
    Then the response status code is 400
    And the response has a "error" property and it is equals "invalid_request"
    And the response has a "error_description" property

  Scenario: Invalid refresh token
    When I create oauth2 request
    And I add the request parameters:
        | grant_type    | refresh_token |
        | refresh_token | foo           |
    And I send a access token request: "POST"
    Then the response status code is 400
    And the response has a "error" property and it is equals "invalid_grant"
    And the response has a "error_description" property

  Scenario: Refreshing OK
    Given that I have an refresh token
    When I create oauth2 request
    And I add the request parameters:
        | grant_type    | refresh_token     |
    And I make a access token request with given refresh token: "POST"
    Then the response status code is 200
    And the response is oauth2 format
    And the response has a "access_token" property
    And the response has a "token_type" property and it is equals "Bearer"
    And the response has a "refresh_token" property
    And the response has a "expires_in" property and its type is numeric
    And the response has a "scope" property
