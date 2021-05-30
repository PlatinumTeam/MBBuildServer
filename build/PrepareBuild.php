<?php

defined("MBB_RUN") or die();

function prepareBuild($workingDir, $build) {
	chdir($workingDir);

	echo("0. Script Pre-processing\n");
	buildMessage("Status: Preparing: 0. Script Pre-processing\n");
	$conts = file_get_contents("./platinum/main.cs");
	$conts = preg_replace('/\$THIS_VERSION = .*?;/', '$THIS_VERSION = ' . $build["Version"] . ';', $conts);
	$conts = preg_replace('/\$THIS_VERSION_NAME = ".*?";/', '$THIS_VERSION_NAME = "' . $build["Name"] . '";', $conts);
	$conts = preg_replace('/\$THIS_VERSION_SUB = ".*?";/', '$THIS_VERSION_SUB = "' . $build["SubVersion"] . '";', $conts);
	file_put_contents("./platinum/main.cs", $conts);

	$conts = file_get_contents("./platinum/shared/mp/defaults.cs");
	$conts = preg_replace('/\$MP::RevisionOn = .*?;/', '$MP::RevisionOn = ' . $build["Revision"] . ';', $conts);
	file_put_contents("./platinum/shared/mp/defaults.cs", $conts);

	Filesystem::filterForEach($workingDir, '.*\.(cs|gui|mcs)$', function ($file) {
		$conts = file_get_contents($file);
		while (true) {
			$start = strpos($conts, '#devstart');
			if ($start === false) {
				break;
			}
			$end = strpos($conts, '#devend', $start);
			if ($end === false) {
				break;
			}
			$conts = substr_replace($conts, "", $start, $end - $start + 7 /* strlen("#devend") */);
		}
		file_put_contents($file, $conts);
	});

	echo("1. Compile the build\n");
	buildMessage("Status: Preparing: Compiling first\n");
	`./Contents/MacOS/MBExtender -compileall`;

	Filesystem::copy("./platinum/cliend/scripts/redundancyCheck.cs", "../../redundancyCheck.cs");

	echo("2. Delete CS/GUI files\n");
	Filesystem::filterForEach($workingDir, '.*\.(cs|gui|mcs)$', function ($file) {
		if (strpos($file, "/dev/") === false) {
			Filesystem::delete($file);
		}
	});

	echo("3. Delete .DS_Store and Thumbs.db\n");
	Filesystem::deleteMatching($workingDir, '(\.DS_Store|Thumbs\.db)$');

	echo("4. Delete model and image source files\n");
	Filesystem::deleteMatching($workingDir, '\.(psd|ms3d|blend|log|orig)$');

	echo("5. Clean up root directory\n");
	Filesystem::move("Change Log.txt", "clog");
	$deletions = [
		'\.txt$', '\.php$', '\.key$', '\.sh$', '\.bat$', '\.png$', '\.torsion$', '\.ico$', '\.command$',
		'sublime', 'mbcrypt.exe', 'MBCrypt', 'Setup', 'editor'
	];
	foreach ($deletions as $pattern) {
		Filesystem::deleteMatching($workingDir, $pattern, false);
	}
	Filesystem::move("clog", "Change Log.txt");

	echo("6. Clean up platinum directory\n");
	$deletions = [
		"platinum/client/config.cs.dso",
		"platinum/client/lbprefs.cs.dso",
		"platinum/client/mbpprefs.cs.dso",
		"platinum/client/prefs.cs.dso",
		"platinum/core/editor/WEprefs.cs.dso",
		"platinum/data/.nullfile",
		"platinum/data/interiors-custom",
		"platinum/data/interiors_pq/custom",
		"platinum/data/missions_pq/custom",
		"platinum/data/missions/custom",
		"platinum/data/missions/Haxmission.mis",
		"platinum/data/missions/higuy",
		"platinum/data/missions/test",
		"platinum/data/missions/testMission.mis",
		"platinum/data/multiplayer/elimination",
		"platinum/data/multiplayer/race",
		"platinum/data/multiplayer/seek",
		"platinum/data/multiplayer/skies",
		"platinum/data/multiplayer/tag",
		"platinum/leaderboards/multiplayer/prefs.cs.dso",
		"platinum/MBP.tqb",
	];
	foreach ($deletions as $file) {
		Filesystem::delete($file);
	}
	//Extra buildy things
	Filesystem::deleteMatching($workingDir . "/platinum", '\.(sh|php)$');

	//Delete all contents, not the directory itself
	$deletions = [
		"platinum/client/demos",
		"platinum/data/screenshots",
	];
	foreach ($deletions as $dir) {
		//Match blank == everything
		Filesystem::deleteMatching($workingDir . "/" . $dir, '');
	}

	echo("7. Apply -final directories\n");
	$finals = [
		"platinum/data/multiplayer/hunt/custom-final",
		"platinum/data/multiplayer/interiors/custom-final",
	];
	foreach ($finals as $dir) {
		$unfinal = str_replace("-final", "", $dir);
		if (is_dir($dir)) {
			echo("Overwrite $unfinal with $dir\n");
			Filesystem::delete($unfinal);
			Filesystem::move($dir, $unfinal);
		}
	}

	echo("8. Create package files\n");
	generatePackages($workingDir);

	echo("9. Install package files\n");
	copyPackages($workingDir);

	echo("10. Mark files as executable\n");
	chmod("Contents/MacOS/MBExtender", 0755);
	chmod("Contents/MacOS/MarbleBlast Gold", 0755);
	Filesystem::filterForEach("Contents/MacOS/plugins/", '.*', function($file) {
		chmod($file, 0755);
	});

	echo("11. CRC one last time\n");
	buildMessage("Status: Preparing: Compiling after changes\n");
	`./Contents/MacOS/MBExtender -compileall -sha`;

	echo("12. Delete dev files\n");
	Filesystem::delete("platinum/dev");
	Filesystem::delete("platinum/client/scripts/redundancycheck.cs");
	Filesystem::delete("console.log");

}

function backupBuild($buildDir, $backupDir, $build) {
	$backupSubDir = $backupDir . "/{$build["Name"]}/{$build["Revision"]}";
	mkdir($backupSubDir, 0775, true);

	$lastMessage = gettimeofday(true);
	buildMessage("Status: Backing Up\n");

	$dir = opendir($buildDir);
	while (($entry = readdir($dir)) !== false) {
		if ($entry === "." || $entry === "..") {
			continue;
		}

		$buildFile = "$buildDir/$entry";
		$backupFile = "$backupSubDir/$entry";

		echo("Backing up: $backupFile\n");

		copy($buildFile, $backupFile);
		if (gettimeofday(true) - $lastMessage > 5) {
			$lastMessage = gettimeofday(true);
			buildMessage("Status: Backing Up: $entry\n");
		}
	}
	closedir($dir);

}
