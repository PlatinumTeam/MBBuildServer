<?php

defined("MBB_RUN") or die();

//Copy to Config.php before using

$config = [
	//Build directory settings
	"repoBase"   => dirname(__DIR__) . "/repo_base",
	"workingDir" => dirname(__DIR__) . "/working_dir",
	"buildDir"   => dirname(__DIR__) . "/build_output",
	"gameDir"    => "/game_subdir_in_repo",

	//Pak creation settings
	"packageCachePath" => dirname(__DIR__) . "/package_cache",
	"packageTmpPath"   => dirname(__DIR__) . "/package_tmp",
	"mbCryptPath"      => dirname(__DIR__) . "/MBCrypt",
	"privateKeyPath"   => dirname(__DIR__) . "/mbcrypt_private_key",

	//Git executables
	"gitPath"    => "/usr/bin/git",
	"gitLFSPath" => "/usr/bin/git-lfs",

	//Discord bot settings
	"discordChannel"       => 200000000000000000,
	"discordPinnedMessage" => 200000000000000000,
	"discordToken"         => "Bot longTokenStringHere",

	//Upload settings
	"uploadKeyPath" => "/path/to/ssh_private_key",
	"remotePort"    => 12345,
	"remoteHost"    => "host",
	"remoteUser"    => "user",
	"remoteBase"    => "/tmp/uploadbase",

	//Backup settings
	"backup"    => false,
	"backupDir" => "/mnt/backups/pq",

	//Listing location and settings
	"listingConfig" => "https://example.com/config/config.json",
	"listingBase"   => "https://example.com/base/dir",
	"listingName"   => "gamefiles",
];
