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

use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;

class ProcessTimedOutException extends SymfonyProcessTimedOutException implements ExceptionInterface
{
	public function __construct(SymfonyProcessTimedOutException $exception)
	{
		parent::__construct($exception->getProcess(), $exception->isGeneralTimeout() ? self::TYPE_GENERAL : self::TYPE_IDLE);
	}
}
