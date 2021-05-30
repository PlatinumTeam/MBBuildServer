<?php

defined("MBB_RUN") or die();

function fetchRepo($repoBase) {
	chdir($repoBase);
	//Get the latest commits
	if (return_value(GIT . " fetch --all --tags") !== 0) {
		discordMessage("FAIL: Fetch failed");
		die("Fetch failed\n");
	}
}

function getRepo($repoBase, $tag) {
	//Get into the game's directory
	chdir($repoBase);

	if (return_value(GIT . " clean -fdx") !== 0) {
		discordMessage("FAIL: Clean failed");
		die("Clean failed\n");
	}
	if (return_value(GIT . " reset --hard") !== 0) {
		discordMessage("FAIL: Reset failed");
		die("Reset failed\n");
	}
	//Get the latest commits
	if (return_value(GIT . " checkout master") !== 0) {
		discordMessage("FAIL: Checkout failed");
		die("Checkout failed\n");
	}
	//And pull
	if (return_value(GIT . " pull origin master") !== 0) {
		discordMessage("FAIL: Pull failed");
		die("Pull failed\n");
	}
	// Tag if necessary
	$tagCmd = GIT . " tag -l $tag";
	if (`$tagCmd` === null) {
		discordMessage("FAIL: Tag $tag not found (did you forget to push the tag first?)");
		die("Tag $tag not found\n");
	}
	//And update LFS data
	if (return_value(GIT_LFS . " fetch") !== 0) {
		discordMessage("FAIL: LFS fetch failed");
		die("LFS fetch failed\n");
	}
	//And update LFS data
	if (return_value(GIT_LFS . " checkout") !== 0) {
		discordMessage("FAIL: LFS checkout failed");
		die("LFS checkout failed\n");
	}
	echo("Got latest repo\n");
}

function cleanBuild($workingDir, $gameDir) {
	//Clean up a previous build
	if (is_dir($workingDir)) {
		buildMessage("Status: Cleaning");

		echo("Cleaning up previous working directory\n");
		Filesystem::delete($workingDir);
	}

	if (!is_dir($workingDir)) {
		buildMessage("Status: Copying");

		//Copy the game to our working dir
		echo("Copying game to working directory\n");
		Filesystem::copy($gameDir, $workingDir);
		chmod($workingDir . "/Contents/MacOS/MBExtender", 0755);
		chmod($workingDir . "/Contents/MacOS/MarbleBlast Gold", 0755);
	}

	$hackDir = realpath(pathinfo($workingDir, PATHINFO_DIRNAME) . "/hack_override");
	if (is_dir($hackDir)) {
		Filesystem::filterForEach($hackDir, ".*", function ($file) use($hackDir, $workingDir) {
			$newLoc = str_replace($hackDir, $workingDir, $file);
			Filesystem::copy($file, $newLoc);
		});
	}
}