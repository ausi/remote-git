<?php

namespace Ausi\RemoteGit\Tests;

use Ausi\RemoteGit\Exception\ExecutableNotFoundException;
use Ausi\RemoteGit\GitExecutable;
use PHPUnit\Framework\TestCase;

class GitExecutableTest extends TestCase
{
	public function testFailsWithEmptyPath(): void
	{
		$this->expectException(ExecutableNotFoundException::class);
		new GitExecutable('');
	}
}
