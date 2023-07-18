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

namespace Ausi\RemoteGit;

use Ausi\RemoteGit\GitObject\Commit;

class Branch
{
	public function __construct(
		private Repository $repo,
		private string $name,
		private string $commitHash,
	) {
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getCommit(): Commit
	{
		return $this->repo->getCommit($this->commitHash);
	}
}
