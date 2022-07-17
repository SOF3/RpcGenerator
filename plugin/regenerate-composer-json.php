<?php

chdir(__DIR__);
$data = json_decode(file_get_contents("composer.json"));
$files = [];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("src")) as $file) {
	if($file->getExtension() === "php") {
		$files[] = $file->getPathName();
	}
}
$data->autoload->files = $files;
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents("composer.json", $json);
