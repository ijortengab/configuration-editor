<?php

/**
 * Example: Create Simple Sequence Data.
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
$resource = tmpfile(__DIR__ . '/example04.ini');
$config = ConfigurationEditor::load(['file' => $resource, 'format' => 'ini']);

// Lets see current content.
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Lets see current data.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);

// Set new data.
$config->setData('island', 'Sumatera');
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

/**
 * Line: 25
 * var_dump((string) $config):
 * string(191) "; This is comment.
 * country = Indonesia ;This is inline comment.
 * islands[] = Sumatera
 * islands[] = Jawa
 * islands[] = Borneo
 * islands[] = Sulawesi ;This is inline comment.
 * islands[] = Papua
 * "
 */
/**
 * Line: 28
 * var_dump($config->getData()):
 * array(2) {
 *     ["country"]=>
 *     string(9) "Indonesia"
 *     ["islands"]=>
 *     array(5) {
 *         [0]=>
 *         string(8) "Sumatera"
 *         [1]=>
 *         string(4) "Jawa"
 *         [2]=>
 *         string(6) "Borneo"
 *         [3]=>
 *         string(8) "Sulawesi"
 *         [4]=>
 *         string(5) "Papua"
 *     }
 * }
 */
/**
 * Line: 39
 * var_dump($config->getData()):
 * array(8) {
 *     ["country"]=>
 *     string(9) "Indonesia"
 *     ["islands"]=>
 *     array(5) {
 *         [0]=>
 *         string(8) "Sumatera"
 *         [1]=>
 *         string(4) "Jawa"
 *         [2]=>
 *         string(6) "Borneo"
 *         [3]=>
 *         string(8) "Sulawesi"
 *         [4]=>
 *         string(5) "Papua"
 *     }
 *     ["island"]=>
 *     string(8) "Sumatera"
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
 * string(298) "; This is comment.
 * country = Indonesia ;This is inline comment.
 * islands[] = Sumatera
 * islands[] = Jawa
 * islands[] = Borneo
 * islands[] = Sulawesi ;This is inline comment.
 * islands[] = Papua
 * island = Sumatera
 * about = province
 * [] = Aceh
 * [] = Sumatera Utara
 * [] = Sumatera Barat
 * [] = Sumatera Selatan
 * "
 */
