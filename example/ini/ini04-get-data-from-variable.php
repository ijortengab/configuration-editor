<?php

/**
 * Example: Get data from variable.
 *
 * Attention: Run ```composer install``` first before execute this file.
 * @see: example/README.md
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/var_dump.php';
use IjorTengab\ConfigurationEditor\ConfigurationEditor;
use IjorTengab\ConfigurationEditor\Formats\INI;
use function IjorTengab\Override\PHP\VarDump\var_dump;
use const IjorTengab\Override\PHP\VarDump\SUBJECT;
use const IjorTengab\Override\PHP\VarDump\COMMENT;
use const IjorTengab\Override\PHP\VarDump\LINE;

// Init.
$string = file_get_contents(__DIR__ . '/example01.ini');
$config = new ConfigurationEditor(new INI($string));

// Lets see current content.
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Lets see current data.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);

/**
 * Line: 24
 * var_dump((string) $config):
 * string(52) "about = gender
 * gender[] = male
 * gender[] = female
 * "
 */
/**
 * Line: 27
 * var_dump($config->getData()):
 * array(2) {
 *     ["about"]=>
 *     string(6) "gender"
 *     ["gender"]=>
 *     array(2) {
 *         [0]=>
 *         string(4) "male"
 *         [1]=>
 *         string(6) "female"
 *     }
 * }
 */
