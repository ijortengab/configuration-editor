<?php

/**
 * Example: Create Advanced Data.
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
$config->setData('island[sumatera][about]', 'province');
$config->setData('island[sumatera][province][]', 'Aceh');
$config->setData('island[sumatera][province][]', 'Sumatera Utara');
$config->setData('island[sumatera][province][]', 'Sumatera Barat');
$config->setData('island[sumatera][province][]', 'Sumatera Selatan');

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
 * Line: 38
 * var_dump($config->getData()):
 * array(3) {
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
 *     array(1) {
 *         ["sumatera"]=>
 *         array(2) {
 *             ["about"]=>
 *             string(8) "province"
 *             ["province"]=>
 *             array(4) {
 *                 [0]=>
 *                 string(4) "Aceh"
 *                 [1]=>
 *                 string(14) "Sumatera Utara"
 *                 [2]=>
 *                 string(14) "Sumatera Barat"
 *                 [3]=>
 *                 string(16) "Sumatera Selatan"
 *             }
 *         }
 *     }
 * }
 */
/**
 * Line: 42
 * var_dump((string) $config):
 * string(402) "; This is comment.
 * country = Indonesia ;This is inline comment.
 * islands[] = Sumatera
 * islands[] = Jawa
 * islands[] = Borneo
 * islands[] = Sulawesi ;This is inline comment.
 * islands[] = Papua
 * island[sumatera][about] = province
 * island[sumatera][province][] = Aceh
 * island[sumatera][province][] = Sumatera Utara
 * island[sumatera][province][] = Sumatera Barat
 * island[sumatera][province][] = Sumatera Selatan
 * "
 */
