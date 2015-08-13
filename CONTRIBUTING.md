# Contributing

## Setup repository

1. Fork the [rstgroup/oauth2-behat-tests] repository on github.
2. Clone your fork locally and enter it (use your own github username in the statement below)

    ```
    $ git clone git@github.com:rstgroup/oauth2-behat-tests.git
    $ cd oauth2-behat-tests
    ```
   
3. Add a remote to the canonical OAuth2 Behat Tests Libary repository, so you can keep your fork up-to-date:

    ```
    $ git remote add api-rest ssh://git@github.com:rstgroup/oauth2-behat-tests.git
    $ git fetch oauth2-behat-tests
    ```
   
## Working on Api REST Library

Working on Api REST Library using gitflow workflow is obligatory.

A typical workflow will then consist of the following:

1. Create a new local branch based off your `master` or `develop` branch (see [What branch to issue the pull request against?](#what-branch-to-issue-the-pull-request-against)).
2. Switch to your new local branch. (This step can be combined with the previous step with the use of git checkout -b.)
3. Do some work, commit, repeat as necessary.
4. Push the local branch to your remote repository.
5. Send a pull request.

### What branch to issue the pull request against?

* For fixes against the stable release, issue the pull request against the `master` branch.
* For new features, or fixes that introduce new elements to the public API (such as new public methods or properties), issue the pull request against the `develop` branch.