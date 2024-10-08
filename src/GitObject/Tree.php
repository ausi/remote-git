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

namespace Ausi\RemoteGit\GitObject;

use Ausi\RemoteGit\Exception\InvalidArgumentException;
use Ausi\RemoteGit\Exception\InvalidGitObjectException;
use Ausi\RemoteGit\Exception\InvalidPathException;

final class Tree extends GitObject
{
	private const EMPTY_TREE_HASH = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

	private string|null $treeContent = null;

	public static function getTypeName(): string
	{
		return 'tree';
	}

	public function commit(string $message, Commit ...$parents): Commit
	{
		return $this->getRepo()->commitTree($this, $message, ...$parents);
	}

	/**
	 * @throws InvalidGitObjectException
	 * @throws InvalidPathException
	 */
	public function getFile(string $path): File|self|null
	{
		$pathSegments = explode('/', trim($path, '/'), 2);

		if (\count($pathSegments) > 1) {
			$tree = $this->getFile($pathSegments[0]);

			if ($tree instanceof File) {
				throw new InvalidPathException(\sprintf('Invalid path "%s", "%s" is not a directory', $path, $pathSegments[0]));
			}

			return $tree?->getFile($pathSegments[1]);
		}

		$treeContent = $this->loadTreeContent();

		for (
			$offset = 0, $length = \strlen($treeContent);
			$offset < $length;
			$offset = $nextNull + 21
		) {
			$nextSpace = strpos($treeContent, ' ', $offset);
			$nextNull = strpos($treeContent, "\0", $offset);

			if ($nextSpace === false || $nextNull === false || $nextSpace > $nextNull || $nextNull + 20 >= $length) {
				throw new InvalidGitObjectException(\sprintf('Invalid tree object %s.', $this->getHash()));
			}

			if ($pathSegments[0] === substr($treeContent, $nextSpace + 1, $nextNull - $nextSpace - 1)) {
				$hash = bin2hex(substr($treeContent, $nextNull + 1, 20));

				if (ltrim(substr($treeContent, $offset, $nextSpace - $offset), '0') === '40000') {
					return new self($this->getRepo(), $hash);
				}

				return new File($this->getRepo(), $hash);
			}
		}

		return null;
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws InvalidGitObjectException
	 * @throws InvalidPathException
	 */
	public function withFile(string $path, File|self|string $file, bool $executable = false): self
	{
		if (!$file instanceof GitObject) {
			$file = $this->getRepo()->createObject($file);
		}

		if ($executable && !$file instanceof File) {
			throw new InvalidArgumentException(\sprintf('Only files can be marked as executable, expected "%s" got "%s"', File::class, $file::class));
		}

		$pathSegments = explode('/', trim($path, '/'));

		if (\count($pathSegments) > 1) {
			$subtree = $this->getFile($pathSegments[0]) ?? new self($this->getRepo(), self::EMPTY_TREE_HASH);

			if ($subtree instanceof File) {
				throw new InvalidPathException(\sprintf('Invalid path "%s", "%s" is not a directory', $path, $pathSegments[0]));
			}

			$path = array_shift($pathSegments);
			$file = $subtree->withFile(implode('/', $pathSegments), $file, $executable);
		}

		$treeByPath = [];
		$treeContent = $this->loadTreeContent();

		for (
			$offset = 0, $length = \strlen($treeContent);
			$offset < $length;
			$offset = $nextNull + 21
		) {
			$nextSpace = strpos($treeContent, ' ', $offset);
			$nextNull = strpos($treeContent, "\0", $offset);

			if ($nextSpace === false || $nextNull === false || $nextSpace > $nextNull || $nextNull + 20 >= $length) {
				throw new InvalidGitObjectException(\sprintf('Invalid tree object %s.', $this->getHash()));
			}

			$treeByPath['/'.substr($treeContent, $nextSpace + 1, $nextNull - $nextSpace - 1)] = [
				bin2hex(substr($treeContent, $nextNull + 1, 20)),
				substr($treeContent, $offset, $nextSpace - $offset),
			];
		}

		$mode = '40000';

		if ($file instanceof File) {
			$mode = $executable ? '100755' : '100644';
		}

		$treeByPath['/'.$path] = [$file->getHash(), $mode];
		$treeContent = '';

		foreach ($this->sortTree($treeByPath) as $itemPath => $item) {
			$treeContent .= $item[1].' '.substr($itemPath, 1)."\0".hex2bin($item[0]);
		}

		return $this->getRepo()->createObject($treeContent, self::class);
	}

	/**
	 * The entries in a tree are ordered in the _path_ order,
	 * which means that a directory entry is ordered
	 * by adding a slash to the end of it.
	 *
	 * So a directory called "a" is ordered
	 * _after_ a file called "a.c",
	 * because "a/" sorts after "a.c".
	 *
	 * @see https://github.com/git/git/blob/5e238546dc7a232d8998f1cd1ec9d3f4a0add68b/fsck.c#L478-L485
	 *
	 * @param array<string, array{string, string}> $treeByPath
	 *
	 * @return array<string, array{string, string}>
	 */
	private function sortTree(array $treeByPath): array
	{
		uksort(
			$treeByPath,
			static function (string $a, string $b) use ($treeByPath): int {
				$a .= (int) substr($treeByPath[$a][1], -6, -4) === 4 ? '/' : '';
				$b .= (int) substr($treeByPath[$b][1], -6, -4) === 4 ? '/' : '';

				return $a <=> $b;
			},
		);

		return $treeByPath;
	}

	private function loadTreeContent(): string
	{
		if ($this->treeContent === null) {
			$this->treeContent = $this->getRepo()->readObject($this->getHash(), self::class);
		}

		return $this->treeContent;
	}
}
