<?php

declare(strict_types=1);

/*
 * This file is part of the ausi/remote-git package.
 *
 * (c) Martin Auswöger <martin@auswoeger.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
