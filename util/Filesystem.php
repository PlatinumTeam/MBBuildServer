<?php

defined("MBB_RUN") or die();

class Filesystem {
	public static $test = false;

	/**
	 * Recursively remove a directory
	 * @param string $dir
	 */
	static function rmdir($dir) {
		if (is_dir($dir)) {
			echo("Remove directory $dir\n");
			$files = scandir($dir);
			foreach ($files as $file) {
				if ($file != "." && $file != "..") {
					self::rmdir("$dir/$file");
				}
			}
			rmdir($dir);
		} else if (file_exists($dir)) {
			unlink($dir);
		}
	}

	/**
	 * Recursively copy a directory to another place
	 * @param string $src Directory to copy
	 * @param string $dst Destination path
	 */
	static function copy($src, $dst) {
		if (file_exists($dst)) {
			self::rmdir($dst);
		}
		if (is_dir($src)) {
			echo("Copying directory $src\n");
			mkdir($dst);
			$files = scandir($src);
			foreach ($files as $file) {
				if ($file != "." && $file != "..") {
					self::copy("$src/$file", "$dst/$file");
				}
			}
		} else if (file_exists($src)) {
			copy($src, $dst);
		}
	}

	/**
	 * Move a file or directory
	 * @param string $src Path to move
	 * @param string $dst Destination path
	 */
	static function move($src, $dst) {
		echo("Move $src to $dst\n");
		rename($src, $dst);
	}

	/**
	 * Remove all files in a directory matching a regex pattern
	 * @param string  $dir     Directory to search
	 * @param string  $pattern Pattern to delete files that match
	 * @param boolean $recurse If the deletion should recurse directories
	 */
	static function deleteMatching($dir, $pattern, $recurse = true) {
		if (is_dir($dir)) {
			$files = scandir($dir);
			foreach ($files as $file) {
				if ($file != "." && $file != "..") {
					if ($recurse) {
						self::deleteMatching("$dir/$file", $pattern, $recurse);
					} else if (file_exists($file)) {
						if (preg_match("/$pattern/", $file) === 1) {
							self::delete($file);
						}
					}
				}
			}
		} else if (file_exists($dir)) {
			if (preg_match("/$pattern/", $dir) === 1) {
				self::delete($dir);
			}
		}
	}

	/**
	 * Apply a callback to every matching file in a directory
	 * @param string $dir     Directory to search for files
	 * @param string $pattern Pattern to match files against
	 * @param mixed  $func    Function that is called for every matching file
	 */
	static function filterForEach($dir, $pattern, $func) {
		if (is_dir($dir)) {
			$files = scandir($dir);
			foreach ($files as $file) {
				if ($file != "." && $file != "..") {
					self::filterForEach("$dir/$file", $pattern, $func);
				}
			}
		} else if (file_exists($dir)) {
			if (preg_match("/$pattern/", $dir) === 1) {
				$func($dir);
			}
		}
	}

	/**
	 * Delete a file or directory
	 * @param string $path Path to delete
	 */
	static function delete($path) {
		if (!is_file($path) && !is_dir($path))
			return;

		echo("Delete $path\n");

		if (!self::$test) {
			self::rmdir($path);
		}
	}
}
