<?php

define("MBB_RUN", true);

require("Config.php");

require("../util/Process.php");
require("../util/Filesystem.php");
require("../util/DiscordBot.php");
require("../util/Options.php");

require("GitCommands.php");
require("PrepareBuild.php");
require("MakePackages.php");
require("MakeListing.php");
require("UploadBuild.php");

function prompt($message) {
	if (PROMPT) {
		echo("$message\nPress enter to continue...\n");
		readline();
	}
}

echo("Got options:\n");
print_r($options);

$listingConfig = optDefault($options, "listingConfig", $config["listingConfig"]);
$listingBase   = optDefault($options, "listingBase",   $config["listingBase"]);
$listingName   = optDefault($options, "listingName",   $config["listingName"]);
$listingDir    = optDefault($options, "listingDir",    getListingDir($listingConfig, $listingName, $listingBase));

$repoBase       = optDefault($options, "repoBase",       $config["repoBase"]);
$workingDir     = optDefault($options, "workingDir",     $config["workingDir"]);
$buildDir       = optDefault($options, "buildDir",       $config["buildDir"]);
$gameDir        = optDefault($options, "gameDir",        $config["gameDir"]);
$backupDir      = optDefault($options, "backupDir",      $config["backupDir"]);

$fetch    = (bool)optDefault($options, "fetch",    true);
$checkout = (bool)optDefault($options, "checkout", true);
$clean    = (bool)optDefault($options, "clean",    true);
$prepare  = (bool)optDefault($options, "prepare",  true);
$list     = (bool)optDefault($options, "list",     true);
$compress = (bool)optDefault($options, "compress", true);
$upload   = (bool)optDefault($options, "upload",   true);
$backup   = (bool)optDefault($options, "backup",   $config["backup"]);

//Figure out what tag we're going to be using
if ($fetch) {
	discordMessage("Fetching repo...\n");
	fetchRepo($repoBase);
}
//Latest tagged commit
//$tag = trim(`git for-each-ref --sort=-taggerdate --format '%(tag)' refs/tags --count 1`);
$tag = "master";
$tag = optDefault($options, "tag", $tag);

echo("Git object to check out:        " . $tag                           . "\n");
echo("Run Checkout:                   " . ($checkout ? "True" : "False") . "\n");

if ($checkout) {
    prompt("Running checkout, confirm cleaning and checking out in $repoBase");
    echo("Checking out latest repo...\n");
	discordMessage("Checking out latest repo...\n");
	getRepo($repoBase, $tag);
	echo("Checked out latest repo.\n");
}

//Load packages
loadPackages($repoBase);

//Get build name
chdir($repoBase);
$buildName       = optDefault($options, "buildName",       trim(`git for-each-ref --sort=-taggerdate --format '%(tag)' refs/tags --count 1`));
$buildVersion    = optDefault($options, "buildVersion",    (int)preg_replace('/[^0-9]/i', '', $buildName));
$buildSubVersion = optDefault($options, "buildSubVersion", "v" . ((int)trim(`git log --pretty=format:'%H' | wc -l`) + 2700));
$buildRevision   = optDefault($options, "buildRevision",   (int)trim(`git log --pretty=format:'%H' | wc -l`) + 2700); //Why not

echo("Here are the input parameters:\n");
echo("Git object to check out (done): " . $tag                           . "\n");
echo("Run Checkout (done):            " . ($checkout ? "True" : "False") . "\n");
echo("Run Clean:                      " . ($clean    ? "True" : "False") . "\n");
echo("Run Prepare:                    " . ($prepare  ? "True" : "False") . "\n");
echo("Run List:                       " . ($list     ? "True" : "False") . "\n");
echo("Run Compress:                   " . ($compress ? "True" : "False") . "\n");
echo("Run Upload:                     " . ($upload   ? "True" : "False") . "\n");
echo("Run Backup:                     " . ($backup   ? "True" : "False") . "\n");
echo("\n");
echo("Repository Base (dirty):        " . $repoBase        . "\n");
echo("Working Directory (copy):       " . $workingDir      . "\n");
echo("Build Directory (final):        " . $buildDir        . "\n");
echo("Game Subdirectory:              " . $gameDir         . "\n");
echo("Listing URL (base):             " . $listingDir      . "\n");
echo("Backup Directory:               " . $backupDir       . "\n");
echo("Build Name:                     " . $buildName       . "\n");
echo("Build Version:                  " . $buildVersion    . "\n");
echo("Build Sub-version:              " . $buildSubVersion . "\n");
echo("Build Revision:                 " . $buildRevision   . "\n");
echo("\n");

prompt("Starting build");

echo("Starting build $buildName $buildSubVersion\n");

$start = gettimeofday(true);

if ($clean) {
    echo("Cleaning build...\n");
	cleanBuild($workingDir, $repoBase . $gameDir);
	echo("Cleaned working dir.\n");
}
if ($prepare) {
    echo("Preparing build...\n");
	prepareBuild($workingDir, [
        "Name" => $buildName,
        "Version" => $buildVersion,
        "SubVersion" => $buildSubVersion,
        "Revision" => $buildRevision
    ]);
	echo("Build prepared.\n");
}
if ($list) {
    echo("Listing build...\n");
	makeListing($workingDir, $buildDir, $listingDir, $compress);
	echo("Build listed\n");
}

if ($upload) {
    //Now upload it??
    uploadBuild($buildDir, $listingDir);
}
if ($backup) {
	backupBuild($buildDir, $backupDir, [
		"Name" => $buildName,
		"Version" => $buildVersion,
		"SubVersion" => $buildSubVersion,
		"Revision" => $buildRevision
	]);
}
buildMessage("Status: Done in " . (gettimeofday(true) - $start) . " seconds.");

echo("Full build took " . (gettimeofday(true) - $start) . " seconds.\n");
