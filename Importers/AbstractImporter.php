<?php

namespace Import\Yml\Importers;

use Bitrix\Main\Diag\Debug;
use Import\Yml\Interfaces\Executable;
use Import\Yml\Writers\WriterInterface;
use Import\Yml\Readers\ReaderInterface;

abstract class AbstractImporter implements Executable
{
    /**
     * @var null|ReaderInterface
     */
    protected $reader = null;

    /**
     * @var null|WriterInterface
     */
    protected $writer = null;

    /**
     * В конструкторе обязательно должны быть взываны 2 метода setReader и setWriter
     */
    abstract public function __construct();

    /**
     * @param ReaderInterface $reader
     * @return AbstractImporter
     */
    public function setReader(ReaderInterface $reader)
    {
        $this->reader = $reader;

        return $this;
    }

    /**
     * @param WriterInterface $writer
     * @return AbstractImporter
     */
    public function setWriter(WriterInterface $writer)
    {
        $this->writer = $writer;

        return $this;
    }

    /**
     * @return ReaderInterface
     */
    public function getReader()
    {
        return $this->reader;
    }

    /**
     * @return WriterInterface
     */
    public function getWriter()
    {
        return $this->writer;
    }

    public function execute()
    {
		if (ONDEBUG) logger('=== Начало импорта категорий ' . date('d.m.Y H:i:s') . ' ===');
        Debug::startTimeLabel('categories');
        $this->writer->addCategories();
        Debug::endTimeLabel('categories');

		if (ONDEBUG) logger('=== Начало импорта товаров ' . date('d.m.Y H:i:s') . ' ===');
        Debug::startTimeLabel('offers');
        $this->writer->addOffers();
        Debug::endTimeLabel('offers');
    }
}
