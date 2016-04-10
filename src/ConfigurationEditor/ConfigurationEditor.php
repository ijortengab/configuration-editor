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
    public $handler;

    /**
     * Informasi path filename.
     */
    protected $filename;

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
        $this->filename = $handler->getFileName();

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
            $this->handler->saveData();
        }
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
        if ($toggle && is_writable($this->filename)) {
            $this->auto_save = true;
        }
        else {
            $this->auto_save = false;
        }
        return $this;
    }

    /**
     * Menyimpan konfigurasi kedalam file.
     */
    public function save()
    {
        $this->has_changed = false;
        return $this->handler->saveData();
    }

    /**
     * Mendapatkan data.
     */
    public function get($key = null)
    {
        return $this->handler->getData($key);
    }

    /**
     * Mengeset data.
     */
    public function set($key, $value)
    {
        $this->has_changed = true;
        $this->handler->setData($key, $value);
        return $this;
    }

    /**
     * Menghapus data.
     */
    public function del($key)
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
    public static function load($filename, LoggerInterface $log = null)
    {
        if (is_array($filename)) {
            $info = $filename;
        }
        else {
            $info = ['filename' => $filename];
        }

        // Cari format (dalam hal ini adalah extensi dari file).
        if (!isset($info['format'])) {
            $ext = pathinfo($info['filename'], PATHINFO_EXTENSION);
            if (empty($ext)) {
                throw new \InvalidAgumentException('Extension unknown.');
            }
            $info['format'] = $ext;
        }

        $format = strtolower($info['format']);
        FormatHandler::init();
        $handler = FormatHandler::getInstance($format);
        $handler->setFileName($info['filename']);
        return new self($handler, $log);
    }



}
