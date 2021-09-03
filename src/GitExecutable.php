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

use Ausi\RemoteGit\Exception\ExecutableNotFoundException;
use Ausi\RemoteGit\Exception\InvalidGitVersionException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
class GitExecutable
{
	private string $path;
	private ?OutputInterface $debugOutput;

	public function __construct(string $path = null, OutputInterface $debugOutput = null)
	{
		if ($path === null) {
			$path = (new ExecutableFinder)->find('git', '/usr/bin/git');

			if ($path === null) {
				throw new ExecutableNotFoundException();
			}
		}

		$this->path = $path;
		$this->debugOutput = $debugOutput;

		$output = $this->execute(['--version']);
		$version = explode(' ', $output);

		if (
			($version[0] ?? null) !== 'git'
			|| ($version[1] ?? null) !== 'version'
			|| !preg_match('/^\d+\.\d+\.\d+/', $version[2] ?? '', $versionMatch)
		) {
			throw new InvalidGitVersionException($output);
		}

		if (version_compare($versionMatch[0], '2.30.0') < 0) {
			throw new InvalidGitVersionException(sprintf('Git version "%s" is too low, version 2.30.0 or higher is required.', $version[2]));
		}
	}

	/**
	 * @param array<int,string> $arguments
	 *
	 * @throws ProcessFailedException
	 */
	public function execute(array $arguments, string $gitDir = '', string $stdin = ''): string
	{
		$this->debugOutput?->writeln('<fg=magenta>$ '.($stdin !== '' ? '… | ' : '').'git '.implode(' ', $arguments).'</>');

		if ($gitDir !== '') {
			array_unshift($arguments, '--git-dir='.$gitDir);
		}

		$process = new Process([$this->path, ...$arguments]);

		if ($stdin !== '') {
			$process->setInput($stdin);
		}

		$output = $process->mustRun(
			fn (string $type, string $data) => $this->debugOutput?->write($type === 'err' ? $data : '', false, OutputInterface::OUTPUT_RAW)
		)->getOutput();

		if ($this->debugOutput && ($duration = microtime(true) - $process->getStartTime()) > 0.05) {
			$this->debugOutput->writeln('<fg=yellow>duration '.number_format($duration, 3).'s</>'."\n");
		}

		return $output;
	}
}
