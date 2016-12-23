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
$config->setData('country[name]', 'Indonesia');
$config->setData('island[sumatera][area]', 'big');
$config->setData('island[sumatera][province][]', 'Lampung');
$config->setData('island[sumatera][province][1]', 'Riau');
$config->setData('island[jawa][area]', 'big');
$config->setData('island[jawa][province][99]', '...');
$config->setData('islands', '...');

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
 * Line: 38
 * var_dump((string) $config):
 * string(655) "; This is comment.
 * island[sumatera][about] = province
 * island[sumatera][province][] = Aceh
 * island[sumatera][province][] = Riau
 * island[sumatera][province][] = Sumatera Barat
 * island[sumatera][province][] = Sumatera Selatan
 * island[sumatera][province][] = Lampung
 * island[sumatera][area] = big
 * island[jawa][about] = province
 * island[jawa][province][0] = Banten
 * island[jawa][province][1] = Jawa Barat
 * island[jawa][province][2] = DKI Jakarta
 * island[jawa][province][3] = Jawa Tengah
 * island[jawa][province][4] = DI Yogyakarta
 * island[jawa][province][5] = Jawa Timur
 * island[jawa][province][99] = ...
 * island[jawa][area] = big
 * country[name] = Indonesia
 * islands = ...
 * "
 */
