#!/usr/bin/env php
<?php

Phar::interceptFileFuncs();
set_include_path('phar://' . __FILE__ . PATH_SEPARATOR . get_include_path());

$args = $argv;
$pharFilename = array_shift($args);
define('PHAR_FILE', basename($pharFilename));

require_once 'index.php';

__HALT_COMPILER(); ?>