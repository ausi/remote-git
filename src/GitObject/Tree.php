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

final class Tree extends GitObject
{
	private ?string $treeContent = null;

	public static function getTypeName(): string
	{
		return 'tree';
	}

	public function commit(string $message, Commit ...$parents): Commit
	{
		return $this->getRepo()->commitTree($this, $message, ...$parents);
	}

	public function getFile(string $path): File|Tree
	{
		$pathSegments = explode('/', trim($path, '/'), 2);

		if (\count($pathSegments) > 1) {
			$tree = $this->getFile($pathSegments[0]);

			if (!$tree instanceof self) {
				throw new \InvalidArgumentException(sprintf('Invalid path "%s", "%s" is not a directory', $path, $pathSegments[0]));
			}

			return $tree->getFile($pathSegments[1]);
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
				throw new \RuntimeException('Invalid tree object.');
			}

			if ($pathSegments[0] === substr($treeContent, $nextSpace + 1, $nextNull - $nextSpace - 1)) {
				$hash = bin2hex(substr($treeContent, $nextNull + 1, 20));

				if (ltrim(substr($treeContent, $offset, $nextSpace - $offset), '0') === '40000') {
					return new self($this->getRepo(), $hash);
				}

				return new File($this->getRepo(), $hash);
			}
		}

		throw new \InvalidArgumentException(sprintf('"%s" file or directory not found', $pathSegments[0]));
	}

	public function withFile(string $path, string|File|Tree $file, bool $executable = false): self
	{
		if (!$file instanceof GitObject) {
			$file = $this->getRepo()->createObject($file);
		}

		if ($executable && !$file instanceof File) {
			throw new \InvalidArgumentException(sprintf('Only files can be marked as executable, expected "%s" got "%s"', File::class, $file::class));
		}

		$pathSegments = explode('/', trim($path, '/'), 2);

		if (\count($pathSegments) > 1) {
			throw new \RuntimeException('nested paths not yet implementend');
			// $file = …
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
				throw new \RuntimeException('Invalid tree object.');
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

		ksort($treeByPath);

		$treeContent = '';

		foreach ($treeByPath as $itemPath => $item) {
			$treeContent .= $item[1].' '.substr($itemPath, 1)."\0".hex2bin($item[0]);
		}

		return $this->getRepo()->createObject($treeContent, self::class);
	}

	private function loadTreeContent(): string
	{
		if ($this->treeContent === null) {
			$this->treeContent = $this->getRepo()->readObject($this->getHash(), self::class);
		}

		return $this->treeContent;
	}
}
