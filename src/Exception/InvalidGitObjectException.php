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

namespace Ausi\RemoteGit\Exception;

use Ausi\RemoteGit\GitObject\GitObject;

class InvalidGitObjectException extends \RuntimeException implements ExceptionInterface
{
	public static function createForInvalidType(string $type): self
	{
		return new self(
			sprintf(
			'$type must be a class string of type "%s", "%s" given',
			GitObject::class,
			$type
		)
		);
	}
}
