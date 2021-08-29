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

	public function testConnection(): void
	{
		$debugOutput = null;

		if (\in_array('--debug', $_SERVER['argv'], true)) {
			$debugOutput = new StreamOutput(fopen('php://stderr', 'w') ?: throw new \RuntimeException());
			$debugOutput->writeln("\n<fg=yellow>GitExecutable debug output:</>\n");
		}

		$repository = new Repository(
			'https://github.com/symfony/symfony.git',
			$this->tmpDir,
			new GitExecutable(null, $debugOutput)
		);

		$this->assertInstanceOf(
			File::class,
			$file = $repository
				->getBranch('6.0')
				->getCommit()
				->getTree()
				->getFile('src/Symfony/Component/Intl/Resources/data/locales/en.php')
		);

		/** @var File $file */
		$this->assertNotEmpty($file->getContents());

		$tree = $repository
			->getBranch('6.0')
			->getCommit()
			->getTree()
			->withFile('file-created-via-remote-git.txt', "👍\n")
		;

		$this->assertSame(40, \strlen($tree->getHash()));

		$commit = $repository
			->setAuthor('My Name', 'me@example.com')
			->commitTree($tree, 'Test-Commit', $repository->getBranch('6.0')->getCommit())
		;

		$this->assertSame(40, \strlen($commit->getHash()));

		//$commit->push('6.0');

		$debugOutput?->writeln("\n<fg=yellow>End of GitExecutable debug output</>\n");

		$this->assertLessThan(10_000_000, $this->getTmpDirSize());

		unset($repository, $tree, $commit, $file);
		gc_collect_cycles();

		$this->assertSame(0, $this->getTmpDirSize());
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
