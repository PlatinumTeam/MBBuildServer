<?php

defined("MBB_RUN") or die();

/**
 * Detect if a string contains another.
 * @param string $haystack The string to search in
 * @param string $needle The string to search for
 * @return boolean If needle was found in haystack.
 */
function contains($haystack, $needle) {
	return strpos($haystack, $needle) !== FALSE;
}

/**
 * Get the name of the .zip file in which the file is stored. Each file should
 * only match one zip.
 * @param string $file The file whose package to get
 * @return string The name of the package for the given file
 */
function getPackage($file) {
	//Determine which patch it's in

	if (!contains($file, "platinum/")) {
		//Core files
		if (contains($file, "packages/")) {
			//Packages, some are core, some are xmas
			if (contains($file, ".pak") && !contains($file, "boot")) {
				return pathinfo($file, PATHINFO_FILENAME);
			} else {
				//PQ + Boot are "core"
				return "core";
			}
		}
		return "core";
	} else {
		if (contains($file, ".dso") && !contains($file, ".mcs.dso")) {
			//DSO files are in scripts
			return "scripts";
		} else if (contains($file, ".gft")) {
			//GFT (font) files
			return "font";
		} else if (contains($file, "/flair/")) {
			//Flair gets its own category
			return "flair";
		} else if (contains($file, "/data/")) {
			//Stuff in data
			if (strpos($file, "/", strpos($file, "/data/") + 6) !== false) {
				//Music is special from sound
				if (contains($file, "/music/")) {
					return "music";
				}

				//Previews are a bit more precise
				if (contains($file, "data/previews") ||
					contains($file, "data/multiplayer") ||
					contains($file, "data/lbinteriors_custom") ||
					contains($file, "data/skies")) {
					$parts = explode("/", $file);
					$data = array_search("data", $parts);
					return $parts[$data + 1] . "_" . str_replace(" ", "_", $parts[$data + 2]);
				}

				//Sub folder
				$parts = explode("/", $file);
				$data = array_search("data", $parts);
				return $parts[$data + 1];
			} else {

				//The rest is resources
				return "resources";
			}
		} else if (contains($file, ".png") ||
		    contains($file, ".jpg") ||
		    contains($file, ".gif") ||
		    contains($file, ".txt")) {
			//Images not in data are GUI
			return "gui";
		} else if (contains($file, ".json")) {
			//Extra config jsons
			return "json";
		}
	}
	//We got nothing
	echo("Unknown type for file: $file\n");
	return "misc";
}

/**
 * Index a directory and return its listing structure as an array.
 * @param string $dir The directory to search
 * @param boolean $mac If the build is a Mac build (for omitting some core files)
 * @return array The contents of the directory in an array.
 */
function indexDir($dir, $mac) {
	$array = [];

	//Read the contents of dir
	$handle = opendir($dir);
	while (($file = readDir($handle)) !== false) {
		//Ignore all preferences, consoles, .DS_Stores, and current directories
		if ($file == "."               || $file == ".."
		    || $file == ".DS_Store"    || $file == "console.log"
		    || $file == "prefs.cs"     || $file == "lbprefs.cs"
		    || $file == "prefs.cs.dso" || $file == "lbprefs.cs.dso")
			continue;

		//Don't include .dll or .exe files on Mac
		if ($mac && (pathinfo($file, PATHINFO_EXTENSION) == "dll" || pathinfo($file, PATHINFO_EXTENSION) == "exe"))
			continue;
		//Don't include Contents on Windows
		if (!$mac && $file == "Contents")
			continue;

		$path = "$dir/$file";
		if (is_dir($path)) {
			//If it's a directory, its information is all its sub-objects

			//Recurse
			$array[$file] = indexDir($path, $mac);
		} else {
			//If it's a file, store its information in an array
			$array[$file] = [
				"package" => getPackage($path),
				"md5" => md5(file_get_contents($path))
			];
		}
	}
	closedir($handle);

	//Directory contents in an array
	return $array;
}

function listArray($array, $path = "") {
	$list = array();
	foreach ($array as $name => $contents) {
		if (array_key_exists("md5", $contents)) {
			$package = $contents["package"];

			$list[$package][] = $path . $name;
		} else {
			$list = array_merge_recursive($list, listArray($contents, $path . $name . "/"));
		}
	}
	return $list;
}

function collectDeletions($oldList, $newList, $path = "") {
	$deletions = [];
	foreach ($oldList as $oldFile => $oldValue) {
		$isArray = is_array($oldValue) && !array_key_exists("md5", $oldValue);
		$inNew = array_key_exists_case_insensitive($oldFile, $newList);

		//If the old list contains a file the new list does not, then it was deleted
		if (!$inNew) {
			//Mark it as deleted
			$deletions[$oldFile] = "";

			//If this was an array then mark its children as deleted
			if ($isArray) {
				$newValue = []; //Empty since everything is deleted
				$newDeletes = collectDeletions($oldValue, $newValue, $path . "/" . $oldFile);
				//Don't bother if there are no subs
				if (count($newDeletes) > 0) {
					$deletions[$oldFile] = $newDeletes;
				}
			}
		} else {
			//If it's still here, but a directory, we should recurse it
			if ($isArray) {
				//New list's reference to this file
				$newValue = $newList[$oldFile];
				$newDeletes = collectDeletions($oldValue, $newValue, $path . "/" . $oldFile);
				//If we've deleted anything from a subdirectory then add it
				if (count($newDeletes) > 0) {
					$deletions[$oldFile] = $newDeletes;
				}
			}
		}
	}
	return $deletions;
}

function array_key_exists_case_insensitive($key, $array) {
	foreach ($array as $item => $value) {
		if (strcasecmp($item, $key) === 0) return true;
	}
	return false;
}

function combineDeletions($oldDelete, $newDelete, $newList, $path = "") {
	//Need to add everything from $oldDelete to $newDelete that isn't in $newList
	foreach ($oldDelete as $oldFile => $oldValue) {
		$isArray = is_array($oldValue) && !array_key_exists("md5", $oldValue);
		$inNewDelete = array_key_exists_case_insensitive($oldFile, $newDelete);
		$inNewList = array_key_exists_case_insensitive($oldFile, $newList);

		//For files:
		//    if in new delete -> ignore
		// or if in new list -> ignore
		// otherwise -> add
		//For directories:
		// recurse
		// if returned deletion has any items then update
		if ($isArray) {
			//Check if anything below is deleted
			$newDeleteValue = $inNewDelete ? $newDelete[$oldFile] : [];
			$newListValue = $inNewList ? $newList[$oldFile] : [];
			$combined = combineDeletions($oldValue, $newDeleteValue, $newListValue, $path . "/" . $oldFile);

			//If recursing gave us any deleted files then add them
			if (count($combined) > 0) {
				$newDelete[$oldFile] = $combined;
			}
		} else {
			//We already have this file marked deleted
			if ($inNewDelete)
				continue;
			//We added this file back in
			if ($inNewList)
				continue;
			//Mark as deleted (migrate)
			$newDelete[$oldFile] = $oldValue;
		}
	}

	return $newDelete;
}

function combineListings($macList, $winList) {
	$listings = $macList;
	$listings["core-mac"] = $listings["core"];
	$listings["core-win"] = $winList["core"];

	//Don't keep a core directory after we split them
	unset($listings["core"]);

	return $listings;
}

function zipGroups($groups, $base, $outDir) {
	$i = 0;
	foreach ($groups as $group => $files) {
		$i ++;
		buildMessage("Status: Exporting: $i / " . count($groups) . ": $group.zip");
		$zipFile = $outDir . "/" . $group . ".zip";
		$archive = new ZipArchive();
		$archive->open($zipFile, ZipArchive::OVERWRITE | ZipArchive::CREATE);

		foreach ($files as $file) {
			$archive->addEmptyDir(dirname($file));
			$archive->addFile($base . "/" . $file, $file);
		}

		echo("Exporting zip: $zipFile\n");
		$archive->close();
		unset($archive);
	}
}

function makeListing($gameDir, $buildDir, $httpBase, $doCompress) {
	//Create listings
	buildMessage("Status: Listing: 1. Indexing mac");
	$macListing = indexDir($gameDir, true);
	buildMessage("Status: Listing: 1. Indexing windows");
	$winListing = indexDir($gameDir, false);
	buildMessage("Status: Listing: 2. Collecting Packages");

	//Collect package names for each OS
	$macKeys = array_keys(listArray($macListing));
	$winKeys = array_keys(listArray($winListing));
	$macPackages = [];
	$winPackages = [];

	foreach ($macKeys as $key) {
		$path = $httpBase . "/" . ($key === "core" ? "core-mac" : $key) . ".zip";
		$macPackages[$key . ".zip"] = $path;
	}
	foreach ($winKeys as $key) {
		$path = $httpBase . "/" . ($key === "core" ? "core-win" : $key) . ".zip";
		$winPackages[$key . ".zip"] = $path;
	}

	echo("Found " . count($macKeys) . " mac packages.\n");
	echo("Found " . count($winKeys) . " windows packages.\n");

	$macDelete = [];
	$winDelete = [];
	buildMessage("Status: Listing: 3. Finding deletions");

	//If we have a previous list we should load it for updates
	if (is_dir($buildDir)) {
		$oldMacListing = json_decode(file_get_contents("$buildDir/listing-mac.json") ?? "[]", true);
		$oldWinListing = json_decode(file_get_contents("$buildDir/listing-win.json") ?? "[]", true);
		$oldMacDelete = json_decode(file_get_contents("$buildDir/delete-mac.json") ?? "[]", true);
		$oldWinDelete = json_decode(file_get_contents("$buildDir/delete-win.json") ?? "[]", true);

		//Find any files we removed this time
		$macDelete = collectDeletions($oldMacListing, $macListing);
		$winDelete = collectDeletions($oldWinListing, $winListing);

		buildMessage("Status: Listing: 3. Combining deletions");

		//And combine in any files we removed last time
		$macDelete = combineDeletions($oldMacDelete, $macDelete, $macListing);
		$winDelete = combineDeletions($oldWinDelete, $winDelete, $winListing);
	} else {
		echo("Did not find previous build directory. Everything is added.\n");
	}

	if ($doCompress) {
		//Clean up the last build so we can export the new one
		buildMessage("Status: Listing: 4. Cleaning up before export");
		Filesystem::delete($buildDir);
		mkdir($buildDir);
	}

	file_put_contents("$buildDir/listing-mac.json", json_encode($macListing));
	file_put_contents("$buildDir/listing-win.json", json_encode($winListing));
	file_put_contents("$buildDir/packages-mac.json", json_encode($macPackages));
	file_put_contents("$buildDir/packages-win.json", json_encode($winPackages));
	file_put_contents("$buildDir/delete-mac.json", str_replace("[]", "{}", json_encode($macDelete)));
	file_put_contents("$buildDir/delete-win.json", str_replace("[]", "{}", json_encode($winDelete)));

	if ($doCompress) {
		//Combine mac / windows listings, but make sure all mac core files go into core-mac and vice versa
		$groupings = combineListings(listArray($macListing), listArray($winListing));
		zipGroups($groupings, "$gameDir", "$buildDir");
	}
}
