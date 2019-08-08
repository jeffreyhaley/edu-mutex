$path = '.build\composer.phar'
if (-not (Test-Path $path)) {
	Invoke-WebRequest -Uri "https://artifactory.mattersight.local/artifactory/mattersight-binaries/composer/composer.1.4.2.phar" -OutFile ".build\composer.phar"
}

& 'php' '-dextension=php_tokenizer.dll' '.build\composer.phar' 'install'