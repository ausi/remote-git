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

interface GitObjectInterface
{
	public function __construct(Repository $repo, string $hash);

	public static function getTypeName(): string;

	public function getHash(): string;
}
