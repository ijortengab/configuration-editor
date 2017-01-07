<?php

namespace IjorTengab\ConfigurationEditor;

use Psr\Log\LoggerInterface;

interface FormatInterface
{
    /**
     * Method untuk meng-convert instance object menjadi string.
     * Gunakan method ini untuk mengembalikan string dari format
     * configuration alih-alih membuat method baru seperti self::getRaw()
     * atau self::getString().
     */
    public function __toString();

    /**
     * Mengeset file. Bisa berupa string yang merupakan path, atau resource
     * dari stream.
     */
    public function setFile($file);

    /**
     * Membaca file yang setelah dilakukan self::setFile().
     */
    public function readFile();

    /**
     * Mendapatkan informasi file dari informasi yang diset oleh
     * method self::setFile().
     */
    public function getFile();

    /**
     * Menulis baru atau mengedit data berdasarkan key.
     */
    public function setData($key, $value);

    /**
     * Sama seperti self::setData(), namun ini versi massal.
     */
    public function setArrayData(Array $array);

    /**
     * Mendapatkan informasi berdasarkan key.
     */
    public function getData($key);

    /**
     * Menghapus data berdasarkan key.
     */
    public function delData($key);

    /**
     * Menghapus keseluruhan data.
     */
    public function clear();

    /**
     * Menyimpan perubahan ke dalam file atau mengupdate stream jika bertipe
     * resource.
     */
    public function save();

    /**
     * Mengeset object log.
     */
    public function setLog(LoggerInterface $log);

    /**
     * Mendapatkan object log.
     */
    public function getLog();
}
