<?php

namespace Import\Yml\Writers\BitrixWriter;

use CCatalogProduct;
use CFile;
use CIBlockElement;
use CIBlockProperty;
use CMain;
use CPrice;
use Import\Yml\Exceptions\ErrorAddException;
use Import\Yml\Exceptions\ErrorUpdateException;

/**
 * Class OffersWriter класс отвечающий за добавление товаров в базу данных
 * @package Import\Yml\Writers\BitrixWriter
 * @property $goodsPriverIblockId Инфоблок с поставщиками
 * @property $nameGoodsProvider Имя поставщика
 * @property $propertyNameGoodsProvider Имя свойства товара куда будет сохранён поставщик
 * @property  $idGoodsProvider
 */
abstract class OffersWriter implements OffersWriterInterface
{
    /**
     * Настрйки
     *
     * @var array
     */
    protected $options;

    /**
     * Массив добавленных категорий
     *
     * @var array
     */
    protected $categories;

    /**
     * Свойства добавленые в бд
     *
     * @var array
     */
    protected $properties;

    /**
     * Товары добавленные в бд
     *
     * @var array
     */
    protected $products;

    // подсистема работы с товарами, удаленными из выгрузки
    protected $actiondate;
    //protected $propName_LAST_IMPORT_DATE = 'Дата последней загрузки';
    /**
     * OffersWriter constructor.
     *
     * @param array $categories
     * @param array $options
     */
    public function __construct(array $categories, array $options)
    {
        $this->actiondate = date('d.m.y H:i:s');
        $this->categories = $categories;
        $this->options = $options;

        $this->properties = $this->getPropertiesFromDb();
        $this->products = $this->getProductsFromDb();

    }


    /**
     * Деактивируем товары, которых нет в файле выгрузки
     *
     *
     */
    public function __destruct()
    {

      //обновление фасетного индекса
      // $index = \Bitrix\Iblock\PropertyIndex\Manager::createIndexer($this->options['iblock_id']);
      // if($index->startIndex()) {
      //     logger("-------\nПересоздание фасетного индекса");
      //     $index->continueIndex(0); // создание без ограничения по времени
      //     $index->endIndex();
      // }

      return;  // деактивация отключена
        logger("-------\nДеактивация товаров старше {$this->actiondate}");
        $c = 0;
        $res = CIBlockElement::GetList([], [
            'IBLOCK_ID' => $this->options['iblock_id'],
            'ACTIVE' => 'Y',
            '!PROPERTY_LAST_IMPORT_DATE' => $this->actiondate,
        ],
            false, // Без группировки
            false,  //Без постранички
            array('ID') // Выбираем только поля необходимые для показа
        );

        $el = new CIBlockElement;
        while($ar = $res->GetNext()) {
            $el->Update($ar['ID'], ['ACTIVE' => 'N']);  // деактивация товара
            // Обнуляем остаток
            $data = [
                'ID' => $ar['ID'],
                'QUANTITY' => 0
            ];
            if (!CCatalogProduct::Add($data, true)) {
                Logger(
                    $this->getFormatError('Ошибка обнуления остатка товара', $data, true)
                );
            }

            if (!$el->Update($ar['ID'], ['ACTIVE' => 'N'])) {
                Logger(
                    $this->getFormatError('Ошибка деактивации товара', $ar['ID'], true)
                );
            }
            $c++;
        }
        logger("Деактивированно товаров: {$c}\n-------");
    }

    /**
     * Вернёт глобальный экземпляр калсса приложения битрикс
     *
     * @return CMain
     */
    protected function getBitrixGlobalApp()
    {
        global $APPLICATION;

        return $APPLICATION;
    }

    protected function getLastBitrixError()
    {
        $ex = $this->getBitrixGlobalApp()->GetException();

        if (is_object($ex)) {
            return $ex->GetString();
        }

        return 'Не удалось установить ошибку';
    }

    /**
     * Получить и вернёт все товары из бд от этого поставщика
     * индентификация по префиксу в xml_id, вид возвращаемого массива XM_ID => ID
     *
     * @return array
     */
    protected function getProductsFromDb()
    {
        $elements = [];

        $elementsRes = CIBlockElement::GetList([], [
            'IBLOCK_ID' => $this->options['iblock_id'],
            'XML_ID'    => $this->options['prefix_xml_id'] . '%'
        ], false, false, ['ID', 'IBLOCK_ID', 'XML_ID']);

        while ($element = $elementsRes->GetNext()) {
            $elements[$element['XML_ID']] = $element['ID'];
        }

        return $elements;
    }

    /**
     * Получает и возвращает все свойства имеющиеся в бд
     *
     * @return array
     */
    protected function getPropertiesFromDb()
    {
        $properties = [];

        $propertiesRes = CIBlockProperty::GetList(
            ['sort' => 'asc', 'name' => 'asc'],
            ['ACTIVE' => 'Y', 'IBLOCK_ID' => $this->options['iblock_id']]
        );

        while ($prop = $propertiesRes->GetNext()) {
            if (!empty($prop['NAME'])) {
                $name = textlower($prop['NAME']);
                $properties[$name] = $prop;
            }
        }

        return $properties;
    }

    /**
     * Добавляет или обновляет 1 товар в базе данных
     *
     * @param array $offer
     * @throws ErrorAddException
     */
    public function addOrUpdate(array $offer)
    {
      $xmlId = $this->options['prefix_xml_id'] . $offer['id'];

      $el = new CIBlockElement;
      if (!isset($this->products[$xmlId])) {
          // add
          $data = $this->element($offer);
          $id = $el->Add($data);
          if (!$id) {
              throw new ErrorAddException(
                  $this->getFormatError('Ошибка добавления товара: ' . $el->LAST_ERROR, $data)
              );
          }
          $this->products[$xmlId] = $id;

          try {
            $this->addProperties($id, $offer);  // добавляем свойства
          } catch(Exception $e) {
              logger($e->getMessage());
          }

          try {
            $this->addOtherInfo($id, $offer); // прочие параметры, напр доп картинки
          } catch(Exception $e) {
              logger($e->getMessage());
          }

      }  else {
          // update
          $id = $this->products[$xmlId];
          echo $id;
      }

      try {
        $this->addPrices($id, $offer);  // Обновили цены
        $this->addProductInfo($id, $offer); // Обновляем торговый каталог: Количество, вес и закуп цену
      } catch(Exception $e) {
          logger($e->getMessage());
      }

    }

    /**
     * В наследнике переопределить этот метод для подготовки данных.
     * ПЕРЕОПРЕДЕЛИТЬ!
     *
     * @param array $offer
     * @return array
     */
    protected function element(array $offer)
    {
        return null;
    }

    /**
     * Добавялет прочую информацию о товаре
     * ПЕРЕОПРЕДЕЛИТЬ!
     *
     * @param int|string $id
     * @param array $offer
     * @throws ErrorAddException
     */
    protected function addOtherInfo($id, array $offer)
    {
      return null;
    }

    /**
     * Добавялет информацию о товаре
     *
     * @param int|string $id
     * @param array $offer
     * @throws ErrorAddException
     * @todo Добавить еденицу измерения
     * @todo Валюту себестоимости брать из файла выгрузки
     */
    protected function addProductInfo($id, array $offer)
    {  
        $data = [
            'ID'                  => $id,
            'QUANTITY'            => $offer['qty'],
            // 'WEIGHT'              => $offer['weight'],
            'PURCHASING_PRICE'    => $offer['price'],
            'PURCHASING_CURRENCY' => 'RUB'
        ];

        // вес обновляем только если установлен
        if (!empty($offer['weight'])) {
          $data['WEIGHT'] = $offer['weight'];
        }

        if (!CCatalogProduct::Add($data, true)) {
            throw new ErrorAddException(
                $this->getFormatError('Ошибка добавления информции о продукте', $offer, true)
            );
        }
        // добавляем количество на склад
        if (method_exists($this, 'addToStock')) {
            $this->addToStock($id, $offer);
        }
    }

    /**
     * Принимает массив со значениями свойств и добавляет их
     * ПЕРЕОПРЕДЕЛИТЬ!
     *
     * @param $id
     * @param array $offer
     */
    protected function addProperties($id, array $offer)
    {
      return null;
    }

    /**
     * Добавляет одно свойство в бд
     *
     * @param array $data
     * @throws ErrorAddException
     */
    protected function addProperty(array $data)
    {
        $ibp = new CIBlockProperty;
        $name = trim($data['name']);
        $id = $ibp->Add([
            'NAME'             => $name,
            'CODE'             => mb_strtoupper(translit($name, 'ru', ['replace_space' => '_', 'replace_other' => '_'])),
            'IBLOCK_ID'        => $this->options['iblock_id'],
            'ACTIVE'           => 'Y',
            'PROPERT_TYPE'     => 'S',
            'WITH_DESCRIPTION' => 'Y',
            'MULTIPLE'         => 'N'
        ]);

        if (!$id) {
            throw new ErrorAddException(
                $this->getFormatError('Ошибка добавления свойства: ' . $ibp->LAST_ERROR, $data)
            );
        }

        $data = CIBlockProperty::GetByID($id)->GetNext();

        $this->properties[mb_strtolower($data['NAME'])] = $data;
    }

    /**
     * Обновляет или добавляет цены
     *
     * @param int|string $id
     * @param array $offer
     * @throws ErrorAddException
     * @todo Сделать абстрактный метод обновления цены, чтобы не дулировался код
     */
    protected function addPrices($id, array $offer)
    {
        // Обновление розничной цены
        $price = CPrice::GetList(
            [],
            ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => $this->options['price_retail_type_id']]
        )->Fetch();

        // если вдруг цена не заполнена
        $rrc = $offer['retailPrice'];
        if (empty($rrc)) {
          $rrc = (int) $offer['price'] * 2; // умножаем закупку
          logger("В файле поставщика нет розничной цены", $offer);
        }
        $data = [
            'PRODUCT_ID'       => $id,
            'PRICE'            => $rrc,
            'CATALOG_GROUP_ID' => $this->options['price_retail_type_id'],
            'CURRENCY'         => 'RUB'
        ];

        if ($price) {
            $res = CPrice::Update($price['ID'], $data);
        } else {
            $res = CPrice::Add($data);
        }

        if (!$res) {
            throw new ErrorAddException(
                $this->getFormatError(
                    'Ошибка обновления розничной цены',
                    ['offer' => $offer, 'data' => $data, 'price_id' => $price['ID']],
                    true
                )
            );
        }

        // Обновление закупочной цены
        if (isset($this->options['price_std_type_id'])) {
            $price = CPrice::GetList(
                [],
                ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => $this->options['price_std_type_id']]
            )->Fetch();

            $data = [
                'PRODUCT_ID'       => $id,
                'PRICE'            => $offer['price'],
                'CATALOG_GROUP_ID' => $this->options['price_std_type_id'],
                'CURRENCY'         => 'RUB'
            ];

            if ($price) {
                $res = CPrice::Update($price['ID'], $data);
            } else {
                $res = CPrice::Add($data);
            }

            if (!$res) {
                throw new ErrorAddException(
                    $this->getFormatError(
                        'Ошибка обновления оптовой цены',
                        ['offer' => $offer, 'data' => $data, 'price_id' => $price['ID']],
                        true
                    )
                );
            }
        }
    }

    protected function getFormatError($msg, $data, $isGetBitrixError = false)
    {
        if ($isGetBitrixError) {
            return $msg . ': ' . $this->getLastBitrixError() . "\n" . var_export($data, true);
        }

        return $msg . "\n" . var_export($data, true);
    }
}
