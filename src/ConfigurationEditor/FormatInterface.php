<?php

namespace IjorTengab\ConfigurationEditor;

use Psr\Log\LoggerInterface;

interface FormatInterface
{
    /**
     *
     */
    public function __toString();

    /**
     *
     */
    public function setFile($filename);

    /**
     *
     */
    public function getFile();

    /**
     *
     */
    public function setData($key, $value);

    /**
     *
     */
    public function setArrayData(Array $array);

    /**
     *
     */
    public function getData($key);

    /**
     *
     */
    public function delData($key);

    /**
     *
     */
    public function save();

    /**
     *
     */
    public function setLog(LoggerInterface $log);

    /**
     *
     */
    public function getLog();
}
