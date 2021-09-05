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
use Ausi\RemoteGit\GitObject\File;
use Ausi\RemoteGit\Repository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RepositoryTest extends TestCase
{
	private string $tmpDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->tmpDir = __DIR__.'/tmp';

		if (file_exists($this->tmpDir)) {
			(new Filesystem)->remove($this->tmpDir);
		}

		(new Filesystem)->mkdir($this->tmpDir);
	}

	protected function tearDown(): void
	{
		if (file_exists($this->tmpDir)) {
			(new Filesystem)->remove($this->tmpDir);
		}

		parent::tearDown();
	}

	/**
	 * @dataProvider connectionUrlsProvider
	 */
	public function testConnection(string $repoUrl): void
	{
		$debugOutput = null;

		if (\in_array('--debug', $_SERVER['argv'], true)) {
			$debugOutput = new StreamOutput(fopen('php://stderr', 'w') ?: throw new \RuntimeException());
			$debugOutput->writeln("\n<fg=yellow>GitExecutable debug output:</>\n");
		}

		$repository = new Repository(
			$repoUrl,
			$this->tmpDir,
			new GitExecutable(null, $debugOutput)
		);

		$this->assertInstanceOf(
			File::class,
			$file = $repository
				->getBranch('HEAD')
				->getCommit()
				->getTree()
				->getFile('.gitignore')
		);

		/** @var File $file */
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

		/** @var File $file */
		$this->assertSame("🎉\n", $file->getContents());

		try {
			$commit->push('HEAD');
			$this->fail('Pushing should have failed without write access');
		} catch (ProcessFailedException) {
			$debugOutput?->writeln('<fg=green>Failed as expected because of missing write access</>');
		}

		$debugOutput?->writeln("\n<fg=yellow>End of GitExecutable debug output</>\n");

		$this->assertLessThan(10_000_000, $this->getTmpDirSize());

		unset($repository, $tree, $commit, $file);
		gc_collect_cycles();

		$this->assertSame(0, $this->getTmpDirSize());
	}

	/**
	 * @return \Generator<array>
	 */
	public function connectionUrlsProvider(): \Generator
	{
		yield ['https://github.com/torvalds/linux.git'];
		yield ['ssh://git@github.com/torvalds/linux.git'];
		yield ['git@github.com:torvalds/linux.git'];
		yield ['https://gitlab.com/linux-kernel/stable.git'];
		yield ['git@gitlab.com:linux-kernel/stable.git'];
		yield ['file://'.\dirname(__DIR__, 2).'/.git'];
	}

	private function getTmpDirSize(): int
	{
		$size = 0;
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS)
		);

		foreach ($files as $file) {
			$size += $file->getSize();
		}

		return $size;
	}
}
