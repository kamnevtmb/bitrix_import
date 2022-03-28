<?php

namespace Import\Yml\Traits\vlamp;

use CIBlockElement;
use Import\Yml\Exceptions\ErrorAddException;

/**
 * Trait Brand
 * @package Import\Yml\Writers\TextilWriter
 * @property $addedBrands array
 * @const BRAND_IBLOCK_ID
 */
trait BrandsProvider
{
  protected $BrandPropertyName = 'Бренд';

  protected $BrandIBlockID = 9;

  /**
   * Уже добавленные бренды имя => id
   *
   * @var array
   */
  protected $addedBrands = [];

  protected function getOrCreateBrand($name)
  {
      $normalName = translit(textlower($name), 'ru', ['replace_space' => '_', 'replace_other' => '_']);

      // если бренд существует вернёт его ид
      if (isset($this->addedBrands[$normalName])) {
          return $this->addedBrands[$normalName];
      }

      $el = new CIBlockElement;

      $id = $el->Add([
          'NAME' => $name,
          'CODE' => $normalName,
          'ACTIVE' => 'Y',
          'IBLOCK_SECTION_ID' => false,
          'IBLOCK_ID' => $this->BrandIBlockID
      ]);

      if (!$id) {
          throw new ErrorAddException(
              $this->getFormatError('Ошибка добавления бренда: ' . $el->LAST_ERROR, $name)
          );
      }

      $this->addedBrands[$normalName] = $id;

      return $id;
  }

  protected function getBrandsFromDb()
  {
      $res = CIBlockElement::GetList([], [
          'IBLOCK_ID' => $this->BrandIBlockID,
      ], false, false, ['ID', 'NAME', 'CODE']);

      $brands = [];
      while ($e = $res->GetNext()) {
          $name = textlower($e['CODE']);

          $brands[$name] = $e['ID'];
      }
      // logger($brands);
      return $brands;
  }
}
