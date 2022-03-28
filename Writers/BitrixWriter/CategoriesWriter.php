<?php

namespace Import\Yml\Writers\BitrixWriter;

use Exception;
use Bitrix\Main\Type as FieldType;
use Bitrix\Main\Loader;
use CIBlockSection;
use CModule;
use Import\Yml\Exceptions\ErrorAddException;
use Import\Yml\Exceptions\ErrorUpdateException;

/**
 * Class CategoriesWriter класс отвечает за импорт категорий
 * Алгоритм импорта:
 * 1) Получить все внешние коды и ид категорий
 * 2) Построить дерево из пришедших категорий
 * 3) Проходя по дереву проверять если категория существует то обновлять её если нет то добавлять
 *
 * @package Import\Yml\Writers\BitrixWriters
 */
class CategoriesWriter implements CategoriesWriterInterface
{
    /**
     * Массив с параметрами
     *
     * @var array
     */
    protected $options;

    /**
     * Массив с категориями имеющимися в базе данных XML_ID => DATA[]
     *
     * @var array
     */
    protected $addedCategories = [];

    /**
     * Добавленные категории типа NAME => ID
     * @var array
     */
    protected $nameAddedCategories = [];

    /**
     * Массив с категориями из YML файла
     *
     * @var array
     */
    protected $parseCategories;

    /**
     * CategoriesWriter constructor.
     * @param array $parseCategories
     * @param array $options
     */
    public function __construct(array $parseCategories, array $options)
    {
        $this->parseCategories = $parseCategories;
        $this->options = $options;
    }

    /**
     * Вернёт массив с категориями записаными в базу данных вида XML_ID => ID
     *
     * @return array
     */
    public function getAddedCategories()
    {
        return $this->addedCategories;
    }

    /**
     * Рекурсивно обходит дерево и добавляет каждую категорию в базу данных
     *
     * @param $tree
     */
    public function addCategoriesFromTree($tree)
    {
        foreach ($tree as $item) {
            try {
                $this->addOrUpdate($item);
            } catch (Exception $e) {
                logger($e->getMessage());
            }

            if (!empty($item['childs'])) {
                $this->addCategoriesFromTree($item['childs']);
            }
        }
    }

    /**
     * Строит дерево категорий из линейного массива
     *
     * @param array $array
     * @param null|string|int $parentId
     * @return array
     */
    public function getTreeFormArray(array $array, $parentId = 0)
    {
        $tree = [];

        foreach ($array as $item)
        {
            if ($item['parentId'] == $parentId) {
                $tree[$item['id']] = $item;

                $tree[$item['id']]['childs'] = $this->getTreeFormArray($array, $item['id']);
            }
        }

        return $tree;
    }

    /**
     * Получит и вернёт категории которые уже есть в базе данных
     *
     * @return array
     */
    public function getAddedCategoriesFromDb()
    {
        $ar = array('IBLOCK_ID' => $this->options['iblock_id'], 
                  'XML_ID' => $this->options['prefix_xml_id'].'%'
                );
        $categoriesResult = CIBlockSection::GetList(
            [],
            $ar,
            false,
            ['XML_ID', 'ID', 'NAME']
        );
    

        $categories = [];

        while ($category = $categoriesResult->GetNext()) {
            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * Добавляет или обнавляет одну категорию
     *
     * @param array $category
     * @throws ErrorAddException
     * @throws ErrorUpdateException
     */
    public function addOrUpdate(array $category)
    {
        $xmlId = $this->options['prefix_xml_id'] . $category['id'];

        // если категория без имени (такое бывает))
        $cat = (isset($category['value']))? $category['value'] : $xmlId;

        $data = [
            'XML_ID'            => $xmlId,
            'NAME'              => $cat,
            'TIMESTAMP_X'       => new FieldType\DateTime(),
            'IBLOCK_ID'         => $this->options['iblock_id'],
        ];

        $bs = new CIBlockSection;
        if (isset($this->addedCategories[$xmlId])) {
            $id = $bs->Update($this->addedCategories[$xmlId], $data);
            if (!$id) {
                throw new ErrorUpdateException(
                    'Ошибка обновления категории: ' . $category['value'] . ' : ' . $bs->LAST_ERROR
                );
            }
        } else {
            if ($category['parentId']) {
                $parentXmlId = $this->options['prefix_xml_id'] . $category['parentId'];
                $parent = $this->addedCategories[$parentXmlId];
            } else {
                $parent = null;
            }

            $data['CODE'] = translit($category['value']) . randString(3);
            $data['IBLOCK_SECTION_ID'] = $parent;
            $data['ACTIVE'] = 'Y'; // категорию добавляем включенной
            $id = $bs->Add($data);

            if (!$id) {
                throw new ErrorAddException('Ошибка добавления категории: ' . $category['value'] . ' : ' . $bs->LAST_ERROR);
            }

            $this->addedCategories[$xmlId] = $id;
        }
    }

    /**
     * Запускает импорт категорий
	 * 1) Выполняется метод getAddedCategoriesFromDb() для получения категорий существующих в базе
	 * 2) Выполняется метод getTreeFormArray()
	 * 3) Выполняется метод addCategoriesFromTree()
     */
    public function importAll()
    {
        $categories = $this->getAddedCategoriesFromDb();
        //logger($categories);

        $this->addedCategories = array_column($categories, 'ID', 'XML_ID');
        //$this->nameAddedCategories = array_column($categories, 'ID', 'NAME');

        $tree = $this->getTreeFormArray($this->parseCategories);
        //logger($tree);

        $this->addCategoriesFromTree($tree);
    }
}
