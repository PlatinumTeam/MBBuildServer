<?php

defined("MBB_RUN") or die();

//Which tag are we going for here?
$shortOpt = "";
$longOpt = [
	"prompt::",
	"tag::",
	"fetch::",
	"checkout::",
	"clean::",
	"prepare::",
	"packages::",
	"list::",
	"upload::",
	"compress::",
	"backup::",
	"repoBase::",
	"workingDir::",
	"buildDir::",
	"gameDir::",
	"listingDir::",
	"buildName::",
	"buildVersion::",
	"buildSubVersion::",
	"buildRevision::",
	"packageCachePath::",
	"packageTmpPath::",
	"mbCryptPath::",
	"privateKeyPath::",
	"gitPath::",
	"gitLFSPath::",
	"discordChannel::",
	"discordPinnedMessage::",
	"discordToken::",
];
$options = getopt($shortOpt, $longOpt);
if ($options === false) {
	$options = [];
}

define("PROMPT", (bool)optDefault($options, "prompt", php_sapi_name() === "cli"));

define('PACKAGE_CACHE_PATH', optDefault($options, "packageCachePath", $config["packageCachePath"]));
define('PACKAGE_TMP_PATH',   optDefault($options, "packageTmpPath",   $config["packageTmpPath"]));
define('MBCRYPT_PATH',       optDefault($options, "mbCryptPath",      $config["mbCryptPath"]));
define('PRIVATE_KEY_PATH',   optDefault($options, "privateKeyPath",   $config["privateKeyPath"]));
define('UPLOAD_KEY_PATH',    optDefault($options, "uploadKeyPath",    $config["uploadKeyPath"]));

define('GIT',     optDefault($options, "gitPath",    $config["gitPath"]));
define('GIT_LFS', optDefault($options, "gitLFSPath", $config["gitLFSPath"]));

define("DISCORD_CHANNEL",        optDefault($options, "discordChannel",       $config["discordChannel"]));
define("DISCORD_PINNED_MESSAGE", optDefault($options, "discordPinnedMessage", $config["discordPinnedMessage"]));
define("DISCORD_TOKEN",          optDefault($options, "discordToken",         $config["discordToken"]));

define('REMOTE_PORT', optDefault($options, "remotePort", $config["remotePort"]));
define('REMOTE_HOST', optDefault($options, "remoteHost", $config["remoteHost"]));
define('REMOTE_USER', optDefault($options, "remoteUser", $config["remoteUser"]));
define('REMOTE_BASE', optDefault($options, "remoteBase", $config["remoteBase"]));

function optDefault($options, $name, $default) {
	if (array_key_exists($name, $options)) {
		return $options[$name];
	}
	return $default;
}

function getListingDir($listingConfig, $listingName, $listingBase) {
	//Listing dir-- use whatever isn't the current one
	$ch = curl_init($listingConfig);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	if (($res = curl_exec($ch)) !== false && ($json = json_decode($res, true)) !== null) {
		$packagesURL = $json["packages"]["mac"];
		//Find if it's yeahboii-1 or yeahboii-2
		if (preg_match('/\/(' . $listingName . '-.)\//', $packagesURL, $matches)) {
			if ($matches[1] == "$listingName-1") {
				$listingDir = "$listingBase/$listingName-2";
			} else {
				$listingDir = "$listingBase/$listingName-1";
			}
		} else {
			$listingDir = "$listingBase/$listingName-1";
			echo("Error curling current package listing path... using $listingName-1\n");
		}
	} else {
		$listingDir = "$listingBase/$listingName-1";
		echo("Error curling current package listing path... using $listingName-1\n");
	}
	curl_close($ch);

	return $listingDir;
}

