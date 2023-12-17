<?php

namespace App\Helpers;

use App\Helpers\CategoryMap;
use App\Models\Category;

class CategoryUtils
{
    /**
     * group files by category
     *
     * @param array $files : array with the files to group
     * @return array list with files grouped by category
     */
    public function groupFilesByCategory($files)
    {
        $filesIndexed = $this->getFilesIndexed($files);
        $categoryList = $this->getCategoriesTree(0);
        return $this->getCustomFilesData($filesIndexed, $categoryList);
    }

    public function getCategoryRecursiveChildren($categoryId)
    {
        $categoryMap = $this->getCategoryMap();
        $item = $categoryMap->GetCategoryItemById($categoryId);
        return array_merge($item->RecursiveChildrenIdsArray, [$categoryId]);
    }

    /**
     * get a categoryMap object
     * @return {object} category map
     */
    public function getCategoryMap()
    {
        $categories = $this->getCategoriesArray();
        $categoryMap = CategoryMap::GetInstance();
        $categoryMap->mapCategories($categories);
        return $categoryMap;
    }

    /**
     * group files by category id
     * @param array $files list of files
     * @return array a list with the grouped files
     */
    private function getFilesIndexed($files)
    {
        $groupFiles = [];
        foreach ($files as $file) {
            $id = $file->category_id;
            if (!isset($groupFiles[$id])) {
                $groupFiles[$id] = [];
            }
            array_push($groupFiles[$id], $file);
        }
        return $groupFiles;
    }

    /**
     * create an array with custom file objects for the given grouped files and categories
     *
     * @param array $files list of files from which to create the custom ones
     * @param array $categories list of categories
     * @param array list with custom file objects
     */
    private function getCustomFilesData($groupFiles, $categories)
    {
        $list = [];
        foreach ($categories as $category) {
            if (isset($groupFiles[$category->id])) {
                $categoryItem = (object) [
                    'id' => $category->id,
                    'name' => $category->name,
                    'parentPath' => isset($category->parentPath) ? $category->parentPath : '',
                    'parentPathList' => isset($category->parentPathList) ? $category->parentPathList : [],
                    'items' => $groupFiles[$category->id],
                ];
                array_push($list, $categoryItem);
            }
        }

        return $list;
    }

    /**
     * get categories as a tree
     * @return {array} an array with category tree
     */
    private function getCategoriesTree($categoryId, $skipCategoryId = null)
    {
        $categoryMap = $this->getCategoryMap();
        $categoryList = $categoryMap->GetCategoryTreeRecursive($categoryId, $skipCategoryId);
        return $categoryList;
    }

    /**
     * get categories from db, ordered by parent and order_index
     * @return {array} array of category models
     */
    private function getCategoriesArray()
    {
        $categories = Category::select(['id', 'parent_id', 'name', 'slug', 'order_index'])
            ->orderBy('parent_id')
            ->orderBy('order_index')
            ->get()->toArray();

        $categories = array_map(function ($item) {
            return (object) $item;
        }, $categories);

        return $categories;
    }
}
