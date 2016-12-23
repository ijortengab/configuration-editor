<?php

/**
 * Example: Get data from resource.
 *
 * Attention: Run ```composer install``` first before execute this file.
 * @see: example/README.md
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/var_dump.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/tmpfile.php';
use IjorTengab\ConfigurationEditor\ConfigurationEditor;
use function IjorTengab\Override\PHP\VarDump\var_dump;
use const IjorTengab\Override\PHP\VarDump\SUBJECT;
use const IjorTengab\Override\PHP\VarDump\COMMENT;
use const IjorTengab\Override\PHP\VarDump\LINE;
use function IjorTengab\Override\PHP\TmpFile\tmpfile;

// Init.
$resource = tmpfile(__DIR__ . '/example01.ini');
$config = ConfigurationEditor::load(['file' => $resource, 'format' => 'ini']);

// Lets see current content.
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Lets see current data.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);

/**
 * Line: 25
 * var_dump((string) $config):
 * string(52) "about = gender
 * gender[] = male
 * gender[] = female
 * "
 */
/**
 * Line: 28
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
