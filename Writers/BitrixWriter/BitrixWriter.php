<?php

namespace Import\Yml\Writers\BitrixWriter;

use Exception;
use Bitrix\Main\Loader;
use Import\Yml\Readers\ReaderInterface;
use Bitrix\Main\Type as FieldType;
use Import\Yml\Writers\WriterInterface;
use InvalidArgumentException;

/**
 * Class BitrixWriter класс записывающий данные в базу данных битрикса
 *
 * @package Import\Yml\Writers
 */
class BitrixWriter implements WriterInterface
{
    /**
     * Класс чтения yml возвращает данные в виде массива
     *
     * @var ReaderInterface|null
     */
    protected $reader = null;

    /**
     * Дополнительные параметры
     *
     * @var array
     */
    protected $options = [
        // Ид инофрмационного блока куда будет записываться
        'iblock_id' => null,
        // Префикс внешнего кода
        'prefix_xml_id' => null,
        // Ид розничной цены
        'price_retail_type_id' => null,
        // Ид оптовой цены
        'price_std_type_id' => null,
        // Коэф. автоматической наценки на опт. цену
        'markup' => 1
    ];

    /**
     * Класс отвечающий за логику импорта категорий
     *
     * @var null|object|string
     */
    protected $categoriesWriter = null;

    /**
     * Класс отвечающий за логику импорта товаров
     *
     * @var null|object
     */
    protected $offersWriter = null;

    /**
     * BitrixWriter constructor.
     * @param ReaderInterface $reader
     * @param $categoriesWriter
     * @param $offersWriter
     * @param array $options
     * @throws Exception
     */
    public function __construct( ReaderInterface $reader, $categoriesWriter, $offersWriter, array $options ) {
        if (!Loader::includeModule('iblock') ||
            !Loader::includeModule('catalog') ||
            !Loader::includeModule('sale')
        ) {
            throw new Exception('Установите модули информционных блоков, торгового каталога и интернет магазина');
        }

        $this->reader           = $reader;
        $this->categoriesWriter = $categoriesWriter;
        $this->offersWriter     = $offersWriter;
        $this->options          = $options;
    }

    /**
     * Добавляет все категории запускается один раз и создает массив импортируемых категорий
	 * 1) Получает все категории из XML файла в массив categories
	 * 2) Создается объект categoriesWriter указанный при создании объекта
	 * 3) Запускается метод importAll()
     */
    public function addCategories()
    {
        $categories = [];

        while ($category = $this->reader->getNextCategory()) {
            $categories[] = $category;
        }

        $className = $this->categoriesWriter;

        $this->categoriesWriter = new $className($categories, $this->options);

        if (!($this->categoriesWriter instanceOf CategoriesWriterInterface)) {
            throw new InvalidArgumentException(
                'Переменая $categoriesWriter в конструкторе должна имплементрироваться от CategoriesWriterInterface'
            );
        }

        $this->categoriesWriter->importAll();
    }

    /**
     * Добавляет все товары
     */
    public function addOffers()
    {
        $className = $this->offersWriter;
        $this->offersWriter = new $className(
            $this->categoriesWriter->getAddedCategories(),
            $this->options
        );

       if (!($this->offersWriter instanceOf OffersWriterInterface)) {
            throw new InvalidArgumentException(
                'Переменая $offersWriter в конструкторе должна имплементрироваться от OffersWriterInterface'
            );
        }

        $countAdded = 0;
        while ($offer = $this->reader->getNextOffer()) {
            try {
                $this->offersWriter->addOrUpdate($offer);
                $countAdded++;
            } catch(Exception $e) {
                logger($e->getMessage());
            }
        }

        logger('Обновлено товаров: ' . $countAdded);
    }
}
