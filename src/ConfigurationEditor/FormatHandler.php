<?php

namespace IjorTengab\ConfigurationEditor;

class FormatHandler
{
    protected static $formats = [];

    /**
     *
     */
    public static function add(Array $list)
    {
        self::$formats = array_merge(self::$formats, $list);
    }

    /**
     *
     */
    public static function getFormats()
    {
        return self::$formats;
    }

    /**
     *
     */
    public static function init()
    {
        $default = [
            'csv' => __NAMESPACE__ . '\\Formats\\' . 'CSV',
            'yml' => __NAMESPACE__ . '\\Formats\\' . 'YAML',
            'yaml' => __NAMESPACE__ . '\\Formats\\' . 'YAML',
            'json' => __NAMESPACE__ . '\\Formats\\' . 'JSON',
            'ini' => __NAMESPACE__ . '\\Formats\\' . 'INI',
        ];
        self::$formats = array_merge(self::$formats, $default);
    }

    /**
     *
     */
    public function getInstance($format)
    {
        $formats = self::$formats;
        if (array_key_exists($format, self::$formats)) {
            return new self::$formats[$format];
        }
        throw new \InvalidArgumentException('Format not supported.');
    }
}
