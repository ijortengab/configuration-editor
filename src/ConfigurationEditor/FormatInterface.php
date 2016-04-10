<?php

namespace IjorTengab\ConfigurationEditor;

use Psr\Log\LoggerInterface;

interface FormatInterface
{
    /**
     *
     */
    public function setFileName($filename);

    /**
     *
     */
    public function getFileName();

    /**
     *
     */
    public function setData($key, $value);

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
    public function saveData();

    /**
     *
     */
    public function setLog(LoggerInterface $log);

    /**
     *
     */
    public function getLog();


}
