<?php

defined("MBB_RUN") or die();

function return_value($cmd) {
	$spec = [ 1 => array("pipe", "w") ];
	$p = proc_open($cmd, $spec, $files);
	if (is_resource($p)) {
		echo("Output: " . stream_get_contents($files[1]) . "\n");
		$code = proc_close($p);
		echo("Returned $code\n");
		return $code;
	} else {
		echo("Couldn't create resource\n");
		return 255;
	}
}
