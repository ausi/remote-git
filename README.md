Remote Git Library for PHP
==========================

[![Build Status](https://img.shields.io/github/workflow/status/ausi/remote-git/CI/main.svg?style=flat-square)](https://github.com/ausi/remote-git/actions?query=branch%3Amain)
[![Coverage](https://img.shields.io/codecov/c/github/ausi/remote-git/main.svg?style=flat-square)](https://codecov.io/gh/ausi/remote-git)
[![Packagist Version](https://img.shields.io/packagist/v/ausi/remote-git.svg?style=flat-square)](https://packagist.org/packages/ausi/remote-git)
[![Downloads](https://img.shields.io/packagist/dt/ausi/remote-git.svg?style=flat-square)](https://packagist.org/packages/ausi/remote-git)
[![MIT License](https://img.shields.io/github/license/ausi/remote-git.svg?style=flat-square)](https://github.com/ausi/remote-git/blob/main/LICENSE)

This library provides methods to handle git repositories remotely
without having to clone the whole repo.
It uses the Symfony process component to run the git client.

Usage
-----

```php
<?php
use Ausi\RemoteGit\Repository;
use Ausi\RemoteGit\GitObject\File;
use Ausi\RemoteGit\Exception\ConnectionException;

try {
    $repo = new Repository('ssh://git@github.com/ausi/remote-git.git');
} catch(ConnectionException $exception) {
    // Unable to connect to the specified server
}

$headCommit = $repo->getBranch('main')->getCommit();

$newTree = $headCommit
    ->getTree()
    ->withFile(
        'example.txt',
        'Example content…',
    )
;

$newCommit = $repo->commitTree($newTree, 'Add example', $headCommit);

$repo->pushCommit($newCommit, 'main');
```

Installation
------------

To install the library use [Composer][]
or download the source files from GitHub.

```sh
composer require ausi/remote-git
```

[Composer]: https://getcomposer.org/
