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

final class Commit extends GitObject
{
	public static function getTypeName(): string
	{
		return 'commit';
	}

	public function getTree(): Tree
	{
		return $this->getRepo()->getTreeFromCommit($this->getHash());
	}

	public function push(string $branchName, bool $force = false): self
	{
		$this->getRepo()->pushCommit($this, $branchName, $force);

		return $this;
	}
}
