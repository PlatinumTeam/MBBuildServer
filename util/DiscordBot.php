<?php

defined("MBB_RUN") or die();

function curlDiscord($endpoint) {
	$headers = ["Authorization: " . DISCORD_TOKEN, "Content-Type: application/json"];
	$curl = curl_init("https://discordapp.com/api$endpoint");
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	return $curl;
}

function buildMessage($message) {
	if (!function_exists("curl_init")) {
		return;
	}

	global $buildName, $buildSubVersion;
	$message = "Running build $buildName $buildSubVersion...\n" . $message;

	discordMessage($message);
}

$messageId = 0;
function discordMessage($message) {
	global $messageId;

	if (!function_exists("curl_init")) {
		return;
	}

	$data = json_encode(["content" => $message]);
	$curl = curlDiscord("/channels/" . DISCORD_CHANNEL . "/messages/" . DISCORD_PINNED_MESSAGE);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	$result = curl_exec($curl);
	curl_close($curl);

	if ($messageId === 0) {
		$data = json_encode(["content" => $message]);
		$curl = curlDiscord("/channels/" . DISCORD_CHANNEL . "/messages");
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		$result = curl_exec($curl);
		curl_close($curl);
		$result = json_decode($result, true);
		$messageId = $result["id"];
	} else {
		$data = json_encode(["content" => $message]);
		$curl = curlDiscord("/channels/" . DISCORD_CHANNEL . "/messages/{$messageId}");
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		$result = curl_exec($curl);
		curl_close($curl);

	}
}