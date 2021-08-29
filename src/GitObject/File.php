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

class File extends GitObject
{
	public static function getTypeName(): string
	{
		return 'blob';
	}

	public function getContents(): string
	{
		return $this->getRepo()->readObject($this->getHash(), self::class);
	}
}
