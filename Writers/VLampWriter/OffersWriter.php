<?php

namespace Import\Yml\Writers\VLampWriter;

use CCatalogProduct;
use CFile;
use CIBlockElement;
use CIBlockProperty;
use CIBlockSection;
use Import\Yml\Writers\BitrixWriter\OffersWriter as BitrixOffersWriter;
use Import\Yml\Exceptions\ErrorAddException;
use Import\Yml\Exceptions\ErrorUpdateException;
use Import\Yml\Traits\vlamp\BrandsProvider;
use Import\Yml\Traits\vlamp\StocksProvider;


/**
 * Class OffersWriter класс отвечающий за добавление товаров в базу данных
 * @package Import\Yml\Writers\BitrixWriter
 * @property $goodsPriverIblockId Инфоблок с поставщиками
 * @property $nameGoodsProvider Имя поставщика
 * @property $propertyNameGoodsProvider Имя свойства товара куда будет сохранён поставщик
 * @property  $idGoodsProvider
 */
class OffersWriter extends BitrixOffersWriter
{
  use StocksProvider, BrandsProvider;

  // Номер склада для StocksProvider
  protected $stockId = 1;

  /**
   * Имя поставщика для трейта GoodsProvider
   * @var string
   */
  protected $nameGoodsProvider = 'vlamp';

  protected function parseUrl($url) {
    $r  = "^(?:(?P<scheme>\w+)://)?";
    $r .= "(?:(?P<login>\w+):(?P<pass>\w+)@)?";
    $r .= "(?P<host>(?:(?P<subdomain>[\w\.]+)\.)?" . "(?P<domain>\w+\.(?P<extension>\w+)))";
    $r .= "(?::(?P<port>\d+))?";
    $r .= "(?P<path>[\w/]*/(?P<file>\w+(?:\.\w+)?)?)?";
    $r .= "(?:\?(?P<arg>[\w=&]+))?";
    $r .= "(?:#(?P<anchor>\w+))?";
    $r = "!$r!";                                                // Delimiters

    preg_match ( $r, $url, $out );

    return $out;
  }


  /**
   * OffersWriter constructor.
   *
   * @param array $categories
   * @param array $options
   */
  public function __construct(array $categories, array $options)
  {
    $this->addedBrands = $this->getBrandsFromDb();

    if (method_exists($this, 'addGoodsProvider')) {
        $this->addGoodsProvider();
    }

    parent::__construct($categories, $options);
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
       $xmlId = $this->options['prefix_xml_id'] . $offer['id'];// . $offer['param_id'];

       $key = array_search ('Анонс', array_column ($offer['params'], 'name'));
       $val = $offer['params'][$key]['value'];

       $data = [
           'ACTIVE'            => 'Y', // новые товары добавляем активированнными
           'NAME'              => $offer['name'],
           'CODE'              => translit($offer['name'] . '-' . $offer['id']),
           'IBLOCK_ID'         => $this->options['iblock_id'],
           'IBLOCK_SECTION_ID' => $this->categories[$this->options['prefix_xml_id'] . $offer['categoryId']],
           'SORT'              => $offer['sort'],
           'XML_ID'            => $xmlId,
       ];

       if (!empty($offer['pictures'])) {
           $file = CFile::MakeFileArray(reset($offer['pictures']));
           $data['DETAIL_PICTURE'] = $file;
       }

       // добавляем сжатый анонс картинки
       $image_src = $file['tmp_name'];
       $tmp_image = $_SERVER['DOCUMENT_ROOT'] . 'upload/tmp/' . 'molly_preview_image.jpg';

       CFile::ResizeImageFile(
          $image_src,
          $tmp_image,
          array('width'=>170, 'height'=>170),
          BX_RESIZE_IMAGE_PROPORTIONAL
       );

       $file1 = CFile::MakeFileArray($tmp_image);
       $data['PREVIEW_PICTURE'] = $file1;
       //        logger($data);
       return $data;
   }

    /**
    * Добавялет прочую информацию о товаре
    *
    * @param int|string $id
    * @param array $offer
    * @throws ErrorAddException
    * @return true;
    */
    protected function addOtherInfo($id, array $offer)
    {
        $data['LAST_IMPORT_DATE'] = $this->actiondate;
        // загружаем остальные картинки
        reset($offer['pictures']);
        while (next($offer['pictures'])) {
             $file = CFile::MakeFileArray(current($offer['pictures']));
             $data['MORE_PHOTO'][] = $file;
        }

	      CIBlockElement::SetPropertyValuesEx($id, $this->options['iblock_id'], $data);
  	}

    protected function updateOtherInfo($id, array $offer)
    {

      /* $prop = [];
       $db_props = CIBlockElement::GetProperty($this->options['iblock_id'], $id,[],['ACTIVE'=>'Y']);
        while ($ob = $db_props->GetNext()) {
            $prop[textlower($ob['NAME'])] = true;
        }*/
        // update properties  ЭТО КОД ДЛЯ ПОЛНОЙ ВЫГРУЗКИ
        $data = [];
        foreach ($offer['params'] as $param) {
            $name = textlower($param['name']);
            // если в массиве нет такого свойства, то создаем в БД
            if (!isset($this->properties[$name])) {
                $this->addProperty($param);
            }
            // if property empty, then set new value from csv
            $data[$this->properties[$name]['ID']] = [
                'VALUE' => $param['value'],
            ];
        }
        $data['LAST_IMPORT_DATE'] = $this->actiondate;
        $data['NOVINKA'] = $offer['new'];
        $data['AKTSIYA'] = $offer['sale'];
        $data['STARAYA_TSENA'] = $offer['old_price'];
        // выполняем update
        CIBlockElement::SetPropertyValuesEx($id, $this->options['iblock_id'], $data);

        // обновляем картинки если появились новые
        // запрос занчений свойства доп.фото
        $prop_id = $this->properties['дополнительные фото']['ID'];
        $iterator = CIBlockElement::GetPropertyValues($this->options['iblock_id'],
          ['ID' => $id],
          fasle,
          ['ID' => [$prop_id]]);

        // собираем массив имен файлов, кот. записаны в БД для данного товара
        $props = $iterator->Fetch();
        // logger($props);

        $files = [];
        $arFile =[];
        foreach ($props[$prop_id] as $key => $value) {
          $val = CFile::GetFileArray($value);
          $files[] = $val['ORIGINAL_NAME'];
        }
        // logger($files);

        // проходим по картинкам в $offer, если имя картинки есть в собранном массиве, то считаем что эта кртинка есть в БД
        // если нет, то загружаем.
        reset($offer['pictures']);
        while (next($offer['pictures'])) {
          $fname = $this->parseUrl(current($offer['pictures']))['file'];
          if (array_search($fname, $files) === false) {
            // готовим новую картинку и пишем в массив
            $file = CFile::MakeFileArray(current($offer['pictures']));
            $arFile[] = ['VALUE' => $file,
                         'DESCRIPTION' => ''];
          }
        }
        if (!Empty($arFile))
          CIBlockElement::SetPropertyValueCode($id, $prop_id, $arFile);

        // иногда товары перемещаются в другие разделы, обновляем привязку к разделу
        // обновляем SECTION_ID товара
        $new_group_xml_id = $this->options['prefix_xml_id'] . $offer['categoryId'];
        if ($cat = CIBlockSection::GetList(
          [],
          ['XML_ID' => $new_group_xml_id],
          false,
          ['ID']
          )->GetNext())
            CIBlockElement::SetElementSection($id, $cat['ID']);

        // Обновляем новинки
        //$ar['NOVINKA'] = $offer->params['Новинка'];
        //$ar['NOVINKA'] = $offer['new'];
        //обвноялю акции
       // $ar['AKTSIYA'] = $offer['sale'];
        //обвноялю старую цену
        //$ar['STARAYA_TSENA'] = $offer['old_price'];
        //обновляю бренд
        //$ar['BREND'] = $offer['my_brand'];
        //обновляем дату импорта
        //$ar['LAST_IMPORT_DATE'] = $this->actiondate;
        //if (!empty($ar)) {
        //    CIBlockElement::SetPropertyValuesEx($id, $this->options['iblock_id'], $ar);
        //}

    }

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
            $this->updateOtherInfo($id, $offer);
        }

        try {
            $this->addPrices($id, $offer);  // Обновили цены
            $this->addProductInfo($id, $offer); // Обновляем торговый каталог: Количество, вес и закуп цену
        } catch(Exception $e) {
            logger($e->getMessage());
        }

    }

     protected function addProperties($id, array $offer)
     {
         $data = [];

         foreach ($offer['params'] as $param) {
             $name = textlower($param['name']);

             // если в массиве нет такого свойства, то создаем в БД
             if (!isset($this->properties[$name])) {
                 $this->addProperty($param);
             }

             $data[$this->properties[$name]['ID']] = [
                 'VALUE' => $param['value'],
                 'DESCRIPTION' => $param['unit']
             ];
         }

         // Бренд
         try {
           $brandId = $this->getOrCreateBrand($offer['params']['Бренд']['value']);
           $name = textlower($this->BrandPropertyName);
           $data[$this->properties[$name]['ID']] = [
               'VALUE' => $brandId,
               'DESCRIPTION' => ''
           ];
         } catch(Exception $e) {
             logger($e->getMessage());
         }

         // Добавляем свойство поставщик
         if (property_exists($this, 'propertyNameGoodsProvider')) {
             $name = textlower($this->propertyNameGoodsProvider);
             if (!isset($this->properties[$name])) {
                 $this->addProperty([
                     'name' => $this->propertyNameGoodsProvider
                 ]);
             }

             $data[$this->properties[$name]['ID']] = [
                 'VALUE' => $this->idGoodsProvider,
                 'DESCRIPTION' => ''
             ];
         }

         CIBlockElement::SetPropertyValuesEx($id, $this->options['iblock_id'], $data);
     }
}
