<?php

declare(strict_types=1);

/*
 * This file is part of the ausi/slug-generator package.
 *
 * (c) Martin Auswöger <martin@auswoeger.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ausi\RemoteGit\Tests;

use Ausi\RemoteGit\GitExecutable;
use Ausi\RemoteGit\Repository;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
	public function testUsesSysGetTmpDir(): void
	{
		$executable = $this->createMock(GitExecutable::class);
		$executable
			->expects($this->atLeastOnce())
			->method('execute')
			->willReturnCallback(function(array $arguments, string $gitDir = '', string $stdin = ''): string {
				if ($arguments[0] === 'init' && $arguments[1] === '--bare') {
					$this->assertStringStartsWith(rtrim(sys_get_temp_dir(), '/').'/', $arguments[2]);
				}

				return '';
			})
		;

		new Repository('ssh://user@example.com/repo.git', null, $executable);
	}
}
