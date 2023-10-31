Remote Git Library for PHP
==========================

[![Build Status](https://img.shields.io/github/actions/workflow/status/ausi/remote-git/ci.yml?branch=main&style=flat-square)](https://github.com/contao/imagine-svg/actions?query=branch%3A1.x)
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
use Ausi\RemoteGit\Exception\PushCommitException;

$repo = new Repository('ssh://git@github.com/ausi/remote-git.git');

try {
    $repo->connect();
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

try {
    $repo->pushCommit($newCommit, 'main');
} catch(PushCommitException $exception) {
    // Unable to push to the specified remote, e.g. no write access
}
```

Installation
------------

To install the library use [Composer][]
or download the source files from GitHub.

```sh
composer require ausi/remote-git
```

Speed
-----

Speed comparison
of cloning <https://gitlab.com/linux-kernel/stable.git>
and reading the contents of a file:

| Command                  | Network | Disk Space |  Time |
|:-------------------------|--------:|-----------:|------:|
| `git clone`              | 3.21 GB |     4.6 GB | 901 s |
| `git clone --depth 1`    |  207 MB |     1.4 GB |  77 s |
| `git clone -n --depth 1` |  207 MB |     216 MB |  46 s |
| `getBranch(…)->getCommit()->getTree()->getFile(…)->getContents()` | 2.49 MB | 2.57 MB | 5.8 s |

Naturally, this is strongly dependent on many factors
like bandwidth and CPU power
and should only give a rough idea of this projects purpose,
namely reading or writing small bits of data from or to
a remote GIT repository.

[Composer]: https://getcomposer.org/
