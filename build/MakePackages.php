<?php

defined("MBB_RUN") or die();

require("Package.php");

$packageList = [];
$packageContents = [];
$installedPackages = [];

function loadPackages($repoBase) {
	global $installedPackages, $packageContents;

	$buildFile = $repoBase . "/build-packages.json";
	$conts = json_decode(file_get_contents($buildFile), true);

	if ($conts === null) {
		throw new Exception("Error loading packages!");
	}

	$packageContents = $conts["contents"];
	$installedPackages = $conts["installed"];
}

function generatePackages($workingDir) {
	global $packageList, $packageContents;
	chdir($workingDir);

	foreach ($packageContents as $name => $contents) {
		$packageList[$name] = new Package($name, $contents);
	}

	foreach ($packageList as $name => $package) {
		/* @var Package $package */
		buildMessage("Status: Creating Packages: $name\n");

		$package->runPak();
		$package->cleanFrom($workingDir);
	}
}

function copyPackages($workingDir) {
	global $installedPackages, $packageList;
	buildMessage("Status: Installing Packages: " . implode(" ", $installedPackages) . "\n");

    foreach ($installedPackages as $name) {
	    $gamePath = $workingDir . "/packages/" . $name . ".pak";
	    $package = $packageList[$name];
	    /* @var Package $package */
	    Filesystem::copy($package->getPakFile(), $gamePath);
    }
}
