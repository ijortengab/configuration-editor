<?php

namespace IjorTengab\ConfigurationEditor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Configuration Editor is active record for file that use to store
 * configuration. We support for CSV, JSON, YML, and INI.
 * All documentation of comment still exists although configuration edited.
 */
class ConfigurationEditor
{
    /**
     * log berupa object yang mengimplements Psr\Log\LoggerInterface.
     */
    protected $log;

    /**
     * Object yang mengimplements FormatInterface.
     */
    protected $handler;

    /**
     * Penanda apakah akan dilakukan autosave atau tidak. Autosave akan berjalan
     * bila object destruct atau eksekusi PHP Script telah selesai.
     */
    protected $auto_save = false;

    /**
     * Penanda apakah configurasi telah terjadi modifikasi (termasuk penambahan
     * dan penghapusan). Nilai ini dijadikan true oleh method ::set() dan
     * ::del().
     */
    protected $has_changed = false;

    /**
     * Construct.
     */
    public function __construct(FormatInterface $handler, LoggerInterface $log = null)
    {
        // Handler must implements FormatInterface.
        $this->handler = $handler;

        // Log must implements LoggerInterface;
        if (null === $log) {
           $this->log = new NullLogger;
        }
        else {
            $this->log = $log;
        }
        $this->handler->setLog($this->log);

        // Jalankan autosave.
        $this->autoSave(true);
    }

    /**
     * Destruct.
     */
    public function __destruct()
    {
        if ($this->auto_save && $this->has_changed) {
            $this->handler->save();
        }
    }

    /**
     *
     */
    public function __toString()
    {
        return $this->handler->__toString();
    }

    /**
     * Mengambil object log.
     */
    public function getLog()
    {
       return $this->log;
    }

    /**
     * Todo.
     */
    public function condition($condition)
    {
        return $this;
    }

    /**
     * Toggle untuk auto-save.
     */
    public function autoSave($toggle)
    {
        $this->auto_save = $toggle;
        return $this;
    }

    /**
     * Menyimpan konfigurasi kedalam file.
     */
    public function save()
    {
        $this->has_changed = false;
        return $this->handler->save();
    }

    /**
     * Mendapatkan data.
     */
    public function getData($key = null)
    {
        return $this->handler->getData($key);
    }

    /**
     * Mengeset data.
     */
    public function setData($key, $value)
    {
        $this->has_changed = true;
        $this->handler->setData($key, $value);
        return $this;
    }

    /**
     * Mengeset array data.
     */
    public function setArrayData(Array $array)
    {
        $this->has_changed = true;
        $this->handler->setArrayData($array );
        return $this;
    }

    /**
     * Menghapus data.
     */
    public function delData($key)
    {
        $this->has_changed = true;
        $this->handler->delData($key);
        return $this;
    }

    /**
     * Shortcut untuk membuat instance.
     * Jika gagal dikenali formatnya, maka akan dilempar ke
     * \InvalidArgumentException.
     */
    public static function load($mixed, LoggerInterface $log = null)
    {
        if (is_array($mixed)) {
            $info = $mixed;
        }
        else {
            $info = ['file' => $mixed];
        }

        // Cari format (dalam hal ini adalah extensi dari file).
        if (!isset($info['format'])) {
            $ext = pathinfo($info['file'], PATHINFO_EXTENSION);
            if (empty($ext)) {
                throw new InvalidAgumentException('Extension unknown.');
            }
            $info['format'] = $ext;
        }

        $format = strtolower($info['format']);
        FormatHandler::init();
        $handler = FormatHandler::getInstance($format);
        $handler->setFile($info['file']);
        return new self($handler, $log);
    }
}
