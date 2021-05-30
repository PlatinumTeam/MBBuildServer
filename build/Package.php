<?php

defined("MBB_RUN") or die();

class Package {
	protected $name;
	protected $files;
	protected $hash;
	protected $tmpDir;

	public function __construct($name, $contents) {
		$this->name = $name;
		$this->files = [];

		//Find all contents
		foreach ($contents as $pattern) {
			if (is_file($pattern)) {
				$this->files[] = $pattern;
			} else if (is_dir($pattern)) {
				Filesystem::filterForEach($pattern, ".*", function($file) {
					$this->files[] = $file;
				});
			} else {
				echo("Can't find pattern: $pattern\n");
			}
		}

		$this->hash = $this->getHash();
		$this->tmpDir = PACKAGE_TMP_PATH;
	}

	public function getPakFile() {
		return PACKAGE_CACHE_PATH . "/" . $this->name . $this->hash . ".pak";
	}

	public function getHash() {
		$hashes = "";
		foreach ($this->files as $file) {
			$hashes .= hash_file("sha256", $file);
		}
		return hash("sha256", $hashes);
	}

	public function runPak() {
		$pakPath = $this->getPakFile();
		if (is_file($pakPath)) {
			//Hey cool we already got one!
			echo("Using cached pak: $pakPath\n");
			return;
		}

		$workingDir = getcwd();

		echo("To package from {$this->tmpDir} into {$this->getPakFile()}\n");

		$this->copyFiles();
		chdir($this->tmpDir);

		$cmd = MBCRYPT_PATH . " pack " . escapeshellarg($pakPath) . " " . escapeshellarg(PRIVATE_KEY_PATH);

		//Add all files
		Filesystem::filterForEach(".", "", function($file) use(&$cmd) {
			//Strip leading './'
			$file = preg_replace('/^\.\//', '', $file);
			$cmd .= " " . escapeshellarg($file);
		});
		//Execute mbcrypt
		echo("Run command: $cmd\n");
		if (return_value($cmd) !== 0) {
			die("Something went wrong with MBCrypt\n");
		}
		echo("Save pak: $pakPath\n");

		$this->cleanup();

		chdir($workingDir);
	}

	public function cleanFrom($workingDir) {
		foreach ($this->files as $file) {
			$workingFile = $workingDir . "/" . $file;
			Filesystem::delete($workingFile);

			//Clean up extra dirs in case we delete all their files
			$this->deleteEmptyDirs($file);
		}
	}

	protected function deleteEmptyDirs($file) {
		$dir = pathinfo($file, PATHINFO_DIRNAME);
		if (is_dir($dir) && $this->isDirEmpty($dir)) {
			echo("Delete empty dir: $dir\n");
			rmdir($dir);
			$this->deleteEmptyDirs($dir);
		}
	}

	protected function isDirEmpty($dir) {
		$handle = opendir($dir);
		while (($file = readdir($handle)) !== false) {
			if ($file ===  "." || $file === "..")
				continue;
			closedir($handle);
			return false;
		}
		closedir($handle);
		return true;
	}

	protected function copyFiles() {
		mkdir($this->tmpDir, 0777, true);

		//Copy all contents
		foreach ($this->files as $file) {
			$dest = "{$this->tmpDir}/$file";
			$destDir = pathinfo($dest, PATHINFO_DIRNAME);
			if (!is_dir($destDir)) {
				mkdir($destDir, 0777, true);
			}
			Filesystem::copy($file, $dest);
		}
	}

	protected function cleanup() {
		Filesystem::delete($this->tmpDir);
	}
}