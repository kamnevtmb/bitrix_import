<?php

namespace Import\Yml\Readers;
use SimpleXMLElement;
use XMLReader;
use DOMDocument;

/**
 * Class SimpleXmlReader чтение yml файлов с помощью simplexml читает весь файл в память
 * @package Import\Yml\Readers
 */
class VLampYmlReader extends FastYmlReader
{
  /**
 * Вернёт массив с данными товара
 * Массив параметров для Битрикса
  * $data = [
  *     'id'            => (string) $attr->id,
  *     'available'     => (string) $attr->available === 'true' ? true : false,
  *     'name'          => (string) $offer->name,
  *     'barcode'       => Штрихкод,
  *     'description'   => (string) $offer->description,
  *     'categoryId'    => (string) $offer->categoryId,
  *     'price'         => Закуп
  *     'retailPrice'   => Розничная цена
  *     'qty'           => Остаток
  *     'weight'        => Вес
  *     'pictures'      => $this->getPictures($offer) массив с картинками
  *     'params'        => другие параметры товара
  *  ];
  *
  * @return array
  */

  protected function getOfferData($offer)
  {
    $attr = $offer->attributes();
    $data = [
		'id'            => (string) $attr->id,
        'available'     => (string) $attr->available === 'true' ? true : false,
        'name'          => (string) $offer->name,
        'categoryId'    => (string) $offer->categoryId,
        'price'         => 0,
        'retailPrice'   => (string) $offer->price,
        'pictures'      => $this->getPictures($offer),
		'vendor'        => (string) $offer->vendor,
    ];

    // Подготавливаем параметры
    $params = [];

    foreach ($offer->param as $param) {
        $attr = $param->attributes();
        $name = (string) $attr->name;
        switch ($name) {
          case 'Остаток поставщика':
            $data['qty'] = (string) $param;
            break;
          case 'Автоматическая сортировка':
            $data['sort'] = (string) $param;
            break;
          case 'Новинка':
            $data['new'] = (string) $param;
            break;
          case 'Акция':
            $data['sale'] = (string) $param;
            break;
            case 'Старая цена':
                $data['old_price'] = (string) $param;
                break;
            // case 'Бренд':
            //     $data['my_brand'] = (string) $param;
            //     break;
          default:
            $params[$name] = [
                'name' => (string) $attr->name,
//                'unit' => (string) $attr->unit,
                'value' => (string) $param
            ];
            break;
        }
    }

    $data['params'] = $params;
    logger($data);
    return $data;
  }

    protected function getPictures(SimpleXMLElement $offer)
    {
        $pictures = [];

        foreach ($offer->image as $pic) {
            $pictures[] = trim((string) $pic);
        }

        return $pictures;
    }

}
