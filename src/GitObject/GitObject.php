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

use Ausi\RemoteGit\Repository;

abstract class GitObject implements GitObjectInterface
{
	private Repository $repo;

	private string $hash;

	public function __construct(Repository $repo, string $hash)
	{
		$this->repo = $repo;
		$this->hash = $hash;
	}

	public function getHash(): string
	{
		return $this->hash;
	}

	protected function getRepo(): Repository
	{
		return $this->repo;
	}
}
