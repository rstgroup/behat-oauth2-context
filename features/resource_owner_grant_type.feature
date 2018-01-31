@resource_owner_grant_type
Feature: OAuth2 Token Custom Grant Type

  Scenario: Linkedin Token Invalid Grant
    When I create oauth2 request
    And I add resource owner parameters "linkedin"
    And I add the query parameters:
      | access_token | foo |
    And I send a access token request "GET"
    Then the response status code is 400
    And the response has a "error" property and it is equals "invalid_grant"
    And the response has a "error_description" property

  Scenario: Linkedin Token Granted
    When I create oauth2 request
    And I add resource owner parameters "linkedin"
    And I add resource owner valid access token "linkedin"
    And I send a access token request "GET"
    Then the response status code is 200
    And the response is oauth2 format
    And the response has a "access_token" property
    And the response has a "token_type" property and it is equals "bearer"
    And the response has a "refresh_token" property
    And the response has a "expires_in" property and its type is numeric
    And the response has a "scope" property
