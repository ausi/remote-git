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

use Ausi\RemoteGit\Exception\ConnectionException;
use Ausi\RemoteGit\Exception\ProcessFailedException;
use Ausi\RemoteGit\Exception\ProcessTimedOutException;
use Ausi\RemoteGit\GitExecutable;
use Ausi\RemoteGit\GitObject\File;
use Ausi\RemoteGit\GitObject\Tree;
use Ausi\RemoteGit\Repository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Filesystem\Filesystem;

class RepositoryTest extends TestCase
{
	private string $tmpDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->tmpDir = __DIR__.'/tmp';

		if ((new Filesystem)->exists($this->tmpDir)) {
			(new Filesystem)->remove($this->tmpDir);
		}

		(new Filesystem)->mkdir($this->tmpDir);
	}

	protected function tearDown(): void
	{
		if ((new Filesystem)->exists($this->tmpDir)) {
			(new Filesystem)->remove($this->tmpDir);
		}

		parent::tearDown();
	}

	/**
	 * @dataProvider repoUrlsProvider
	 */
	public function testFullCycle(string $repoUrl): void
	{
		$debugOutput = null;

		if (\in_array('--debug', $_SERVER['argv'] ?? [], true)) {
			$debugOutput = new StreamOutput(fopen('php://stderr', 'w') ?: throw new \RuntimeException);
			$debugOutput->writeln("\n<fg=yellow>GitExecutable debug output:</>\n");
		}

		$repository = new Repository(
			$repoUrl,
			$this->tmpDir,
			new GitExecutable(null, $debugOutput),
		);

		$repository->setSshConfig(
			str_rot13(file_get_contents(__DIR__.'/../Fixtures/ssh.key') ?: ''),
			file_get_contents(__DIR__.'/../Fixtures/known_hosts'),
		);

		$this->assertInstanceOf(
			File::class,
			$file = $repository
				->getBranch('HEAD')
				->getCommit()
				->getTree()
				->getFile('.gitignore'),
		);

		$this->assertNotEmpty($file->getContents());

		$tree = $repository
			->getBranch('HEAD')
			->getCommit()
			->getTree()
			->withFile('.gitignore', $file->getContents().date("# Y-m-d H:i:s: 👍\n"))
			->withFile('non/existent/directory/file.txt', "🎉\n")
		;

		$this->assertSame(40, \strlen($tree->getHash()));

		$commit = $repository
			->setAuthor('My Name', 'me@example.com')
			->commitTree($tree, 'Test-Commit', $repository->getBranch('HEAD')->getCommit())
		;

		$this->assertInstanceOf(File::class, $file = $commit->getTree()->getFile('non/existent/directory/file.txt'));

		$this->assertSame("🎉\n", $file->getContents());

		try {
			$commit->push('HEAD');
			$this->fail('Pushing should have failed without write access');
		} catch (ProcessFailedException|ProcessTimedOutException) {
			$debugOutput?->writeln('<fg=green>Failed as expected because of missing write access</>');
		}

		$debugOutput?->writeln("\n<fg=yellow>End of GitExecutable debug output</>\n");

		$this->assertLessThan(10_000_000, $this->getTmpDirSize());

		unset($repository, $tree, $commit, $file);
		gc_collect_cycles();

		$this->assertSame(0, $this->getTmpDirSize());
	}

	/**
	 * @return \Generator<array{0:string}>
	 */
	public function repoUrlsProvider(): \Generator
	{
		yield ['https://github.com/torvalds/linux.git'];
		yield ['ssh://git@github.com/torvalds/linux.git'];
		yield ['git@github.com:torvalds/linux.git'];
		yield ['https://gitlab.com/linux-kernel/stable.git'];
		yield ['git@gitlab.com:linux-kernel/stable.git'];
	}

	public function testSshConfig(): void
	{
		$debugOutput = null;

		if (\in_array('--debug', $_SERVER['argv'] ?? [], true)) {
			$debugOutput = new StreamOutput(fopen('php://stderr', 'w') ?: throw new \RuntimeException);
			$debugOutput->writeln("\n<fg=yellow>GitExecutable debug output:</>\n");
		}

		$repoUrl = iterator_to_array($this->repoUrlsProvider())[1][0];
		$executable = new GitExecutable(null, $debugOutput);

		$repository = new Repository($repoUrl, $this->tmpDir, $executable);

		$repository->setSshConfig('malformed private key', false);

		try {
			$repository->connect();
			$this->fail('Should have thrown an exception with: invalid format or error in libcrypto');
		} catch (ConnectionException $exception) {
			$this->assertTrue(
				str_contains((string) $exception, 'error in libcrypto')
				|| str_contains((string) $exception, 'invalid format'),
			);
		}

		$repository->setSshConfig(
			null,
			'github.com ssh-rsa AAAA',
			'ssh -o GlobalKnownHostsFile=/dev/null', // Disable known hosts on CI
		);

		try {
			$repository->connect();
			$this->fail('Should have thrown an exception with: Host key verification failed');
		} catch (ConnectionException $exception) {
			$this->assertStringContainsString('Host key verification failed', (string) $exception);
		}

		$repository->setSshConfig(null, false, 'php -r "fwrite(STDERR, \'error from command\');exit(1);" --');

		try {
			$repository->connect();
			$this->fail('Should have thrown an exception with: error from command');
		} catch (ConnectionException $exception) {
			$this->assertStringContainsString('error from command', (string) $exception);
		}

		unset($repository, $exception);
		gc_collect_cycles();

		$this->assertSame(0, $this->getTmpDirSize());
	}

	public function testCorrectTreeSorting(): void
	{
		$debugOutput = null;

		if (\in_array('--debug', $_SERVER['argv'] ?? [], true)) {
			$debugOutput = new StreamOutput(fopen('php://stderr', 'w') ?: throw new \RuntimeException);
			$debugOutput->writeln("\n<fg=yellow>GitExecutable debug output:</>\n");
		}

		$repoUrl = iterator_to_array($this->repoUrlsProvider())[1][0];
		$executable = new GitExecutable(null, $debugOutput);

		$repository = new Repository($repoUrl, $this->tmpDir, $executable);
		$gitDirProperty = (new \ReflectionClass($repository))->getProperty('gitDir');
		$gitDirProperty->setAccessible(true);
		$gitDir = $gitDirProperty->getValue($repository);

		$tree = (new Tree($repository, '4b825dc642cb6eb9a060e54bf8d69288fbee4904'))
			->withFile('foo.bar', '')
			->withFile('foo/bar', '')
			->withFile('bar.baz', '')
			->withFile('bar', '')
			->withFile('2', '')
			->withFile('10', '')
			->withFile('a', '')
			->withFile('A', '')
		;

		$this->assertSame(
			implode(
				"\n",
				[
					"100644 blob e69de29bb2d1d6434b8b29ae775ad8c2e48c5391\t10",
					"100644 blob e69de29bb2d1d6434b8b29ae775ad8c2e48c5391\t2",
					"100644 blob e69de29bb2d1d6434b8b29ae775ad8c2e48c5391\tA",
					"100644 blob e69de29bb2d1d6434b8b29ae775ad8c2e48c5391\ta",
					"100644 blob e69de29bb2d1d6434b8b29ae775ad8c2e48c5391\tbar",
					"100644 blob e69de29bb2d1d6434b8b29ae775ad8c2e48c5391\tbar.baz",
					"100644 blob e69de29bb2d1d6434b8b29ae775ad8c2e48c5391\tfoo.bar",
					"040000 tree d87cbcba0e2ede0752bdafc5938da35546803ba5\tfoo",
					'',
				],
			),
			$executable->execute(['ls-tree', $tree->getHash()], $gitDir),
			'Directory "foo" should be sorted after "foo.bar"',
		);

		// Validate tree
		$executable->execute(['fsck'], $gitDir);

		unset($repository, $tree);
		gc_collect_cycles();

		$this->assertSame(0, $this->getTmpDirSize());
	}

	private function getTmpDirSize(): int
	{
		clearstatcache();

		$size = 0;
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
		);

		foreach ($files as $file) {
			$size += $file->getSize();
		}

		return $size;
	}
}
