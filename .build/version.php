<?php
// Get commandline inputs
$version = $argv[1];
$directory = $argv[2];

// Setup static filenames
$composerFilename = 'composer.json';
$composerTestFilename = 'composer.test.json';
$buildinfoFilename = 'buildinfo.json';

// Build the version in composer.json
$file = $directory . DIRECTORY_SEPARATOR . $composerFilename;
$jsonData = file_get_contents($file);
$json = json_decode(trim($jsonData));
$json->version = $version;
$contents = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($file, $contents);

// Build the version in composer.test.json
$file = $directory . DIRECTORY_SEPARATOR . $composerTestFilename;
$jsonData = file_get_contents($file);
$json = json_decode(trim($jsonData));
$json->version = $version;
$contents = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($file, $contents);

// Build the version in buildinfo.json
$file = $directory . DIRECTORY_SEPARATOR . $buildinfoFilename;
$buildinfo = new StdClass();
$buildinfo->name = $json->name;
$buildinfo->description = $json->description;
$buildinfo->version = $json->version;
$buildinfo->dependency = $json->require;
$contents = json_encode($buildinfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($file, $contents);
