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

use Ausi\RemoteGit\Exception\InvalidRemoteUrlException;
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
			->willReturnCallback(
				function (array $arguments): string {
					if ($arguments[0] === 'init' && $arguments[1] === '--bare') {
						$this->assertStringStartsWith(rtrim(sys_get_temp_dir(), '/').'/', $arguments[2]);
					}

					return '';
				}
			)
		;

		new Repository('ssh://user@example.com/repo.git', null, $executable);
	}

	public function testSetConfig(): void
	{
		$config = [];

		$executable = $this->createMock(GitExecutable::class);
		$executable
			->expects($this->atLeastOnce())
			->method('execute')
			->willReturnCallback(
				static function (array $arguments, string $gitDir = '', string $stdin = '') use (&$config): string {
					if ($arguments[0] === 'config') {
						$config[$arguments[1]] = $arguments[2];
					}

					return '';
				}
			)
		;

		$repo = new Repository('ssh://user@example.com/repo.git', null, $executable);

		$this->assertArrayNotHasKey('core.sshCommand', $config);
		$this->assertSame($repo, $repo->setConfig('core.sshCommand', 'ssh -i /path/to/identity_file'));
		$this->assertSame('ssh -i /path/to/identity_file', $config['core.sshCommand']);
	}

	/**
	 * @dataProvider validRemoteUrls
	 */
	public function testValidRemoteUrls(string $url): void
	{
		$remoteCommand = [];

		$executable = $this->createMock(GitExecutable::class);
		$executable
			->expects($this->atLeastOnce())
			->method('execute')
			->willReturnCallback(
				static function (array $arguments, string $gitDir = '', string $stdin = '') use (&$remoteCommand): string {
					if ($arguments[0] === 'remote') {
						$remoteCommand = $arguments;
					}

					return '';
				}
			)
		;

		new Repository($url, null, $executable);

		$this->assertSame(['remote', 'add', 'origin', $url], $remoteCommand);
	}

	/**
	 * @return \Generator<array{0:string}>
	 */
	public function validRemoteUrls(): \Generator
	{
		yield ['https://github.com/torvalds/linux.git'];
		yield ['http://github.com/torvalds/linux.git'];
		yield ['ssh://git@github.com/torvalds/linux.git'];
		yield ['git@github.com:torvalds/linux.git'];
		yield ['github.com:torvalds/linux.git'];
		yield ['https://gitlab.com/linux-kernel/stable.git'];
		yield ['git@gitlab.com:linux-kernel/stable.git'];
		yield ['ftp://github.com/torvalds/linux.git'];
		yield ['ftps://github.com/torvalds/linux.git'];
		yield ['host:repo.git'];
		yield ['https://example.com/with%20space.git'];
	}

	/**
	 * @dataProvider invalidRemoteUrls
	 */
	public function testInvalidRemoteUrls(string $url): void
	{
		$this->expectException(InvalidRemoteUrlException::class);

		new Repository($url, null, $this->createMock(GitExecutable::class));
	}

	/**
	 * @return \Generator<array{0:string}>
	 */
	public function invalidRemoteUrls(): \Generator
	{
		yield [''];
		yield ['1'];
		yield ['123456'];
		yield ['---'];
		yield ['/etc/passwd'];
		yield ['file:///etc/passwd'];
		yield ['ssh://host/path with space.git'];
		yield ['ssh://malformed-port:/repo.git'];
		yield ['ssh://malformed-port:x/repo.git'];
		yield ['ssh://@malformed-user/repo.git'];
		yield ['ssh://?@malformed-user/repo.git'];
		yield ["ssh://invalid/ascii\x00.git"];
		yield ["ssh://invalid/ascii\x1F.git"];
		yield ["ssh://invalid/ascii\x7F.git"];
		yield ["ssh://invalid/ascii\x80.git"];
		yield ["ssh://invalid/ascii\xFF.git"];
	}
}
