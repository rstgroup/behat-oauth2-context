# Behat OAuth2 

This library include Behat context with implemented steps and features to test common use case of server with OAuth2 ([The OAuth 2.0 Authorization Framework standards](https://tools.ietf.org/html/rfc6749)) 

## Requirements

Library is only supported on PHP 5.4.0 and up.

For others requirements please see the [composer.json](composer.json) file.

## Installation

1. Add the following to your `composer.json`,
 
    ```json
    "require": {
        "rstgroup/behat-oauth2-context": "^1.0"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:rstgroup/behat-oauth2-context.git"
        }
    }
    ```

2. Run `composer update rstgroup/behat-oauth2-context` to ensure the library is installed.

## Configuration

Copy behat.yml.dist file as behat.yml to your home project directory or copy contents from this file to your yml file with Behat tests.
You must replace sample content to right option:
```php
    paths:
        - %paths.base%/features
```
to right current path
for example:
```php
    paths:
        - %paths.base%/vendor/rstgroup/behat-oauth2-context/features/
```
And next you must replace parameters option:
- token_url - your url to token
- oauth2 - your data for OAuth2 authorization

### Recommended and optional parameters

In configuration we added two parameters recommended and optional. Their options are exists in OAuth2 RFC.
You can change their values to false if you know that your server doesn't send someone.

## Run Tests

For run tests you must use Behat. You can read about it in [Behat documentation](http://behat.readthedocs.org/en/v3.0/).
Sample run:
```
vendor/bin/behat --config behat.yml
```
