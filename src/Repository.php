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

use Ausi\RemoteGit\Exception\BranchNotFoundException;
use Ausi\RemoteGit\Exception\ConnectionException;
use Ausi\RemoteGit\Exception\InitializeException;
use Ausi\RemoteGit\Exception\InvalidGitObjectException;
use Ausi\RemoteGit\Exception\InvalidRemoteUrlException;
use Ausi\RemoteGit\Exception\ProcessFailedException;
use Ausi\RemoteGit\Exception\RuntimeException;
use Ausi\RemoteGit\GitObject\Commit;
use Ausi\RemoteGit\GitObject\File;
use Ausi\RemoteGit\GitObject\GitObject;
use Ausi\RemoteGit\GitObject\GitObjectInterface;
use Ausi\RemoteGit\GitObject\Tree;
use Symfony\Component\Filesystem\Filesystem;

class Repository
{
	private GitExecutable $executable;
	private string $gitDir;
	private bool $connected = false;
	private ?string $headBranchName = null;

	/**
	 * @var array<Branch>
	 */
	private array $branches = [];

	/**
	 * @param string                    $url               Remote GIT URL, e.g. ssh://user@example.com/repo.git
	 * @param string|null               $tempDirectory     Directory to store the shallow clone, defaults to sys_get_temp_dir()
	 * @param string|GitExecutable|null $gitExecutablePath Path to the git binary, defaults to the path found by the ExecutableFinder
	 */
	public function __construct(string $url, string $tempDirectory = null, string|GitExecutable $gitExecutablePath = null)
	{
		if (!$gitExecutablePath instanceof GitExecutable) {
			$gitExecutablePath = new GitExecutable($gitExecutablePath);
		}

		$this->executable = $gitExecutablePath;
		$this->gitDir = $this->createTempPath($tempDirectory);

		$this->assertValidUrl($url);
		$this->initialize($url);
	}

	public function __destruct()
	{
		// Fix read only files on Windows systems
		if ('\\' === \DIRECTORY_SEPARATOR && file_exists($this->gitDir)) {
			(new Filesystem)->chmod($this->gitDir, 0755, 0000, true);
		}

		(new Filesystem)->remove($this->gitDir);
	}

	/**
	 * @throws ConnectionException
	 */
	public function connect(): static
	{
		if ($this->connected) {
			return $this;
		}

		try {
			$this->run('fetch origin --progress --no-tags --depth 1');
		} catch (ProcessFailedException $e) {
			throw new ConnectionException('Could not connect to git repository.', 0, $e);
		}

		$this->connected = true;

		return $this;
	}

	/**
	 * @throws RuntimeException
	 *
	 * @return array<Branch>
	 */
	public function listBranches(): array
	{
		if ($this->branches) {
			return $this->branches;
		}

		$lines = explode("\n", trim($this->connect()->run('show-ref')));

		foreach ($lines as $line) {
			$cols = explode(' ', $line);

			if (strncmp($cols[1] ?? '', 'refs/remotes/origin/', 20) !== 0) {
				continue;
			}
			$this->branches[trim($cols[1])] = new Branch($this, substr(trim($cols[1]), 20), trim($cols[0]));
		}

		if (!$this->branches) {
			throw new RuntimeException('Unable to list branches');
		}

		return array_values($this->branches);
	}

	/**
	 * @throws BranchNotFoundException
	 */
	public function getBranch(string $name): Branch
	{
		$this->listBranches();

		if ($name === 'HEAD') {
			$name = $this->getHeadBranchName();
		}

		$key = 'refs/remotes/origin/'.$name;

		return $this->branches[$key] ?? throw new BranchNotFoundException();
	}

	/**
	 * @throws RuntimeException
	 */
	public function getHeadBranchName(): string
	{
		if ($this->headBranchName !== null) {
			return $this->headBranchName;
		}

		$this->run('remote set-head origin -a');
		$ref = trim($this->run('symbolic-ref refs/remotes/origin/HEAD'));

		if (strncmp($ref, 'refs/remotes/origin/', 20) !== 0) {
			throw new RuntimeException('Unable to get HEAD branch');
		}

		return $this->headBranchName = substr($ref, 20);
	}

	public function getCommit(string $commitHash): Commit
	{
		return new Commit($this, $commitHash);
	}

	public function getTreeFromCommit(string $commitHash): Tree
	{
		return new Tree(
			$this,
			trim($this->run('rev-parse', $commitHash.'^{tree}')),
		);
	}

	public function commitTree(Tree $tree, string $message, Commit ...$parents): Commit
	{
		$args = [$tree->getHash(), '-m', $message];

		foreach ($parents as $parent) {
			$args[] = '-p';
			$args[] = $parent->getHash();
		}

		return new Commit($this, trim($this->run('commit-tree', ...$args)));
	}

	/**
	 * @template T of GitObjectInterface
	 *
	 * @param class-string<T> $type
	 *
	 * @throws InvalidGitObjectException
	 *
	 * @return T
	 */
	public function createObject(string $contents, string $type = File::class): GitObjectInterface
	{
		if (!is_a($type, GitObjectInterface::class, true)) {
			throw InvalidGitObjectException::createForInvalidType($type);
		}

		$hash = trim($this->runInput($contents, 'hash-object -w --stdin -t', $type::getTypeName()));

		/** @var class-string<T> $type */
		return new $type($this, $hash);
	}

	/**
	 * @param class-string<GitObject> $type
	 *
	 * @throws InvalidGitObjectException
	 */
	public function readObject(string $hash, string $type = File::class): string
	{
		if (!is_a($type, GitObject::class, true)) {
			throw InvalidGitObjectException::createForInvalidType($type);
		}

		return $this->run('cat-file', $type::getTypeName(), $hash);
	}

	public function pushCommit(Commit $commit, string $branchName, bool $force = false): self
	{
		if ($branchName === 'HEAD') {
			$branchName = $this->getHeadBranchName();
		}

		$this->run(
			'push origin --progress',
			'--'.($force ? '' : 'no-').'force-with-lease',
			$commit->getHash().':refs/heads/'.$branchName
		);

		return $this;
	}

	/**
	 * @param string|null $privateKey Identity key to use for the SSH connection or null to use the default of the system
	 * @param string|bool $knownHosts Known hosts to verify against, false disables the host key checking
	 */
	public function setSshConfig(string $privateKey = null, string|bool $knownHosts = true, string $sshCommand = 'ssh'): static
	{
		$keyPath = $this->gitDir.'/.ssh_identity';
		$hostsPath = $this->gitDir.'/.ssh_known_hosts';

		if (\is_string($privateKey)) {
			file_put_contents($keyPath, $privateKey);
			chmod($keyPath, 0600);
			$privateKey = $keyPath;
		}

		if (\is_string($knownHosts)) {
			file_put_contents($hostsPath, $knownHosts);
			$knownHosts = $hostsPath;
		}

		return $this->setSshConfigPaths($privateKey, $knownHosts, $sshCommand);
	}

	/**
	 * @param string|null $privateKeyPath Identity file to use for the SSH connection or null to use the default of the system
	 * @param string|bool $knownHostsPath Known hosts file to verify against, false disables the host key checking
	 */
	public function setSshConfigPaths(string $privateKeyPath = null, string|bool $knownHostsPath = true, string $sshCommand = 'ssh'): static
	{
		if ($privateKeyPath !== null) {
			$sshCommand .= ' -i '.escapeshellarg($privateKeyPath);
		}

		// Disable host key checking
		if ($knownHostsPath === false) {
			$sshCommand .= ' -o CheckHostIP=no';
			$sshCommand .= ' -o StrictHostKeyChecking=no';
		}

		// Custom known hosts file
		if (\is_string($knownHostsPath)) {
			$sshCommand .= ' -o StrictHostKeyChecking=yes';
			$sshCommand .= ' -o GlobalKnownHostsFile=/dev/null';
			$sshCommand .= ' -o '.escapeshellarg('UserKnownHostsFile='.$knownHostsPath);
		}

		return $this->setConfig('core.sshCommand', $sshCommand);
	}

	public function setAuthor(string $name, string $email): static
	{
		$this->setConfig('user.name', $name);
		$this->setConfig('user.email', $email);

		return $this;
	}

	public function setConfig(string $key, string $value): static
	{
		$this->run('config', $key, $value);

		return $this;
	}

	/**
	 * @throws InvalidRemoteUrlException
	 *
	 * @see <https://git-scm.com/docs/git-push#_git_urls>
	 */
	private function assertValidUrl(string $url): void
	{
		if (preg_match('([^\x21-\x7E])', $url)) {
			throw new InvalidRemoteUrlException('Remote URL must not contain non-ASCII or whitespace characters');
		}

		$user = '(?:[^#/?@[\]]+@)?';
		$host = '[a-z0-9-][a-z0-9.-]*[a-z0-9-]';
		$port = '(?::\d+)?';
		$path = '[^/]';

		// GIT, HTTP and FTP protocols
		if (preg_match("(^(?:git|https?|ftps?)://$host$port/$path)i", $url) === 1) {
			return;
		}

		// SSH protocol
		if (preg_match("(^ssh://$user$host$port/$path)i", $url) === 1) {
			return;
		}

		// SSH protocol alternative scp-like syntax
		if (preg_match("(^$user$host:$path)i", $url) === 1) {
			return;
		}

		throw new InvalidRemoteUrlException('Invalid remote URL');
	}

	/**
	 * @throws InitializeException
	 */
	private function initialize(string $url): void
	{
		try {
			$this->executable->execute(['init', '--bare', $this->gitDir]);
			$this->run('remote add origin', $url);
			$this->setConfig('remote.origin.promisor', 'true');
			$this->setConfig('remote.origin.partialclonefilter', 'tree:0');
		} catch (ProcessFailedException $e) {
			throw new InitializeException(sprintf('Unable to initialize git repository "%s".', $this->gitDir), 0, $e);
		}
	}

	private function run(string $command, string ...$args): string
	{
		return $this->runInput('', $command, ...$args);
	}

	private function runInput(string $input, string $command, string ...$args): string
	{
		return $this->executable->execute([...explode(' ', $command), ...array_values($args)], $this->gitDir, $input);
	}

	private function createTempPath(?string $dir): string
	{
		/** @psalm-suppress TooManyArguments */
		$path = (new Filesystem)->tempnam($dir ?? sys_get_temp_dir(), 'repo', '.git');

		(new Filesystem)->remove($path);

		return $path;
	}
}
