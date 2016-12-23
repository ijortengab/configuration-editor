<?php

/**
 * Example: Read mapping data (associative array).
 *
 * Attention: Run ```composer install``` first before execute this file.
 * @see: example/README.md
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/var_dump.php';
use IjorTengab\ConfigurationEditor\ConfigurationEditor;
use function IjorTengab\Override\PHP\VarDump\var_dump;
use const IjorTengab\Override\PHP\VarDump\SUBJECT;
use const IjorTengab\Override\PHP\VarDump\COMMENT;
use const IjorTengab\Override\PHP\VarDump\LINE;

// Init.
$from_path = __DIR__ . '/example03.ini';
$config = ConfigurationEditor::load($from_path);

// Lets see current content.
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Lets see current data.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);

/**
 * Line: 23
 * var_dump((string) $config):
 * string(28) "male = Male
 * female = Female"
 */
/**
 * Line: 26
 * var_dump($config->getData()):
 * array(2) {
 *     ["male"]=>
 *     string(4) "Male"
 *     ["female"]=>
 *     string(6) "Female"
 * }
 */
