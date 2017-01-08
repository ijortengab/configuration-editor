<?php

/**
 * Example: Create New File from Blank.
 *
 * Attention: Run ```composer install``` first before execute this file.
 * @see: example/README.md
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/var_dump.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/tmpfile.php';
use IjorTengab\ConfigurationEditor\ConfigurationEditor;
use IjorTengab\ConfigurationEditor\Formats\INI;
use function IjorTengab\Override\PHP\VarDump\var_dump;
use const IjorTengab\Override\PHP\VarDump\SUBJECT;
use const IjorTengab\Override\PHP\VarDump\COMMENT;
use const IjorTengab\Override\PHP\VarDump\LINE;
use function IjorTengab\Override\PHP\TmpFile\tmpfile;

// There are two alternatives for Init.
// $config = ConfigurationEditor::load(['file' => tmpfile(), 'format' => 'ini']);
$config = new ConfigurationEditor(new INI);

// Lets see current content.
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Lets see current data.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);

// Set new data.
$config->setData('about', 'province');
$config->setData('[]', 'Aceh');
$config->setData('[]', 'Sumatera Utara');
$config->setData('[]', 'Sumatera Barat');
$config->setData('[]', 'Sumatera Selatan');

// Lets see latest data.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);

// Save and see new content.
$config->save();
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Save as new file.
$config->saveAs(__DIR__ . '/my-configuration.ini');

/**
 * Line: 26
 * var_dump((string) $config):
 * string(0) ""
 */
/**
 * Line: 29
 * var_dump($config->getData()):
 * NULL
 */
/**
 * Line: 39
 * var_dump($config->getData()):
 * array(5) {
 *     ["about"]=>
 *     string(8) "province"
 *     [0]=>
 *     string(4) "Aceh"
 *     [1]=>
 *     string(14) "Sumatera Utara"
 *     [2]=>
 *     string(14) "Sumatera Barat"
 *     [3]=>
 *     string(16) "Sumatera Selatan"
 * }
 */
/**
 * Line: 43
 * var_dump((string) $config):
 * string(89) "about = province
 * [] = Aceh
 * [] = Sumatera Utara
 * [] = Sumatera Barat
 * [] = Sumatera Selatan
 * "
 */
