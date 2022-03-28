<?php

namespace Import\Yml\Writers\BitrixWriter;

interface CategoriesWriterInterface
{
    public function addOrUpdate(array $category);

    public function importAll();

    public function getAddedCategories();
}
