<?php

defined("MBB_RUN") or die();

function uploadBuild($buildDir, $listingDir) {
	$keyFile = UPLOAD_KEY_PATH;
	$remotePort = REMOTE_PORT;
	$remoteHost = REMOTE_HOST;
	$remoteUser = REMOTE_USER;
	$remoteBase = REMOTE_BASE;

	$last = substr($listingDir, strrpos($listingDir, '/') + 1);

	$remoteInstallPath = "$remoteBase/$last";

	//Upload everything in buildDir to listingDir
	echo("Uploading build...\n");

	//Clean out remote directory
	$ssh = "ssh -i $keyFile -p $remotePort $remoteUser@$remoteHost";
	`$ssh rm -rf $remoteInstallPath`;
	`$ssh mkdir -p $remoteInstallPath`;

	//Count total files
	$count = 0;
	$dir = opendir($buildDir);
	while (($entry = readdir($dir)) !== false) {
		if ($entry === "." || $entry === "..") {
			continue;
		}
		$count ++;
	}

	//Find stuff to upload
	$commands = [];
	$files = [];

	$dir = opendir($buildDir);
	while (($entry = readdir($dir)) !== false) {
		if ($entry === "." || $entry === "..") {
			continue;
		}

		$localFile = "$buildDir/$entry";
		$remotePath = "$remoteUser@$remoteHost:$remoteInstallPath/$entry";

		$command = "scp -i $keyFile -P $remotePort $localFile $remotePath > /dev/null 2>/dev/null &";
		$commands[] = [$entry, $command];
		$files[$entry] = sha1_file($localFile);
	}
	closedir($dir);

	$per = 50;

	while (count($commands)) {
		$running = [];
		$names = "";

		//Run a few of them
		for ($j = 0; $j < min($per, count($commands)); $j ++) {
			$running[] = $commands[$j];
			`{$commands[$j][1]}`;
			$names .= " {$commands[$j][0]}";

			echo("Upload {$commands[$j][0]}\n");
		}

		buildMessage("Status: Uploading$names");

		//Now wait for all of them to finish
		while (return_value("killall -0 scp") == 0) {
			sleep(1);
		}

		//Check and make sure they're all
		$listCmd = "$ssh 'find \"$remoteInstallPath\" -type f -exec sha1sum \"{}\" \;'";
		echo($listCmd . "\n");
		$list = `$listCmd`;

		/*
2e6b9c080b53b4248d23da5dee066d5096f8516e  /var/www/prod/marbleblast.com/public_html/pq/where/yeahboii/core-mac.zip
123f61013f989c4bd13aa0e19788cda7153cd9d5  /var/www/prod/marbleblast.com/public_html/pq/where/yeahboii/flair.zip
96b112866a414443378b6044021aef4c3bcd286e  /var/www/prod/marbleblast.com/public_html/pq/where/yeahboii/custom_marbles.zip
f3ec4a2a07dbf31ef4969d839d8b0dfa0a04747d  /var/www/prod/marbleblast.com/public_html/pq/where/yeahboii/delete-win.json
4ba68b2422cc27e8b200a6a852160373674f5935  /var/www/prod/marbleblast.com/public_html/pq/where/yeahboii/core-win.zip
7044cdef89e6a518d19e1586dc7888745eba4d74  /var/www/prod/marbleblast.com/public_html/pq/where/yeahboii/font.zip
222308916ffabc5410d8c81418cc2714c09ad481  /var/www/prod/marbleblast.com/public_html/pq/where/yeahboii/gui.zip
f094fceb589a6b212ccd44786702b694f059286b  /var/www/prod/marbleblast.com/public_html/pq/where/yeahboii/delete-mac.json
		 */
		$lines = explode("\n", $list);
		foreach ($lines as $line) {
			if ($line === "")
				continue;

			$components = explode(" ", $line);
			$hash = $components[0];
			$path = array_pop($components);

			$file = substr($path, strrpos($path, "/") + 1);

			$goodHash = $files[$file];

			//Find what it's supposed to be
			for ($j = 0; $j < count($commands); $j ++) {
				$command = $commands[$j];
				if ($command[0] == $file) {
					if ($hash == $goodHash) {
						echo("Match upload: $file\n");
						array_splice($commands, $j, 1);
					}
				}
			}
		}
	}
}
