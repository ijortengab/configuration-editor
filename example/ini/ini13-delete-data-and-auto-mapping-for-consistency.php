<?php

/**
 * Example: Create Simple Data.
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
$resource = tmpfile(__DIR__ . '/example06.ini');
$config = ConfigurationEditor::load(['file' => $resource, 'format' => 'ini']);

// Lets see current content.
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Edit existing data.

$config->delData('islands'); // error disini.
$config->delData('island[sumatera][province][2]');
$config->delData('island[jawa][province][2]');

// Save and see new content.
$config->save();
var_dump((string) $config, SUBJECT | COMMENT | LINE);

/**
 * Line: 25
 * var_dump((string) $config):
 * string(664) "; This is comment.
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
 * island[jawa][about] = province
 * island[jawa][province][] = Banten
 * island[jawa][province][] = Jawa Barat
 * island[jawa][province][] = DKI Jakarta
 * island[jawa][province][] = Jawa Tengah
 * island[jawa][province][] = DI Yogyakarta
 * island[jawa][province][] = Jawa Timur
 * "
 */
/**
 * Line: 35
 * var_dump((string) $config):
 * string(462) "; This is comment.
 * country = Indonesia ;This is inline comment.
 * island[sumatera][about] = province
 * island[sumatera][province][0] = Aceh
 * island[sumatera][province][1] = Sumatera Utara
 * island[sumatera][province][3] = Sumatera Selatan
 * island[jawa][about] = province
 * island[jawa][province][0] = Banten
 * island[jawa][province][1] = Jawa Barat
 * island[jawa][province][3] = Jawa Tengah
 * island[jawa][province][4] = DI Yogyakarta
 * island[jawa][province][5] = Jawa Timur
 * "
 */
