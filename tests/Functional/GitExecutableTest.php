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

namespace Ausi\RemoteGit\Tests\Functional;

use Ausi\RemoteGit\GitExecutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GitExecutableTest extends TestCase
{
	public function testInstantiation(): void
	{
		$this->assertInstanceOf(GitExecutable::class, new GitExecutable);

		$this->expectException(ProcessFailedException::class);

		new GitExecutable('/bin/not-a-git-executable');
	}

	public function testHelpCommand(): void
	{
		$this->assertIsString((new GitExecutable)->execute(['--help']));
	}
}
