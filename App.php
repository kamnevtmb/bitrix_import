<?php

namespace Import\Yml;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Exception;
use Import\Yml\Importers\AbstractImporter;

class App
{
    public function __construct()
    {
        $this->init();
    }
    
    protected function phpSettings()
    {
        ini_set('display_errors', 1);
        ini_set('memory_limit', '3000M');
        set_time_limit(0);
        error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE & ~E_DEPRECATED);
    }

    protected function autoload()
    {
        Loader::includeModule('iblock');
        Loader::includeModule('sale');
        Loader::includeModule('catalog');
    }

    protected function setConstants()
    {
        define('START_TIME', microtime(true));

        if (!defined('LOG_FILENAME')) {
            define('LOG_FILENAME', __DIR__ . '/Resources/logs/' . date('d.m.Y') . '.log');
        }
    }

    protected function init()
    {
        $this->setConstants();
        $this->phpSettings();
        $this->autoload();
    }

    public function start()
    {
        logger('=== Начало импорта от ' . date('d.m.Y H:i:s') . ' ===!');
    }

    public function end()
    {
        $time = Debug::getTimeLabels();

        logger('Время импорта категорий: ' . $time['categories']['time'] . ' сек.');
        logger('Время импорта товаров: ' . $time['offers']['time'] . ' сек.');
        logger('Общее время выполнения: ' . (microtime(true) - START_TIME)  . ' сек.');
        logger('Максимально было выделено памяти: ' . memory_get_peak_usage() / 1024 / 1024 . ' мб.');
        logger('=== Конец импорта ===');
    }

    /**
     * @param \Import\Yml\Interfaces\Executable[] $importers
     */
    public function importArray(array $importers)
    {
        $this->start();

        foreach ($importers as $importer) {
            $this->import($importer);
        }

        $this->end();
    }

    /**
     * @param string $importer
     */
    public function import($importer)
    {
        try {
            /** @var AbstractImporter $import */
            $import = new $importer;
            $import->execute();
        } catch(Exception $e) {
            logger('Ошибка импорта ' . $importer . ' : ' . $e->getMessage());
        }
    }
}
