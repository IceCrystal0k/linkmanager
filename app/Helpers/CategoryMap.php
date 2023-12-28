<?php
namespace App\Helpers;
use App\Helpers\CategoryMapItem;
use App\Helpers\CategoryMapItemBase;

/**
 * Helper class for mapping categories and creating a tree structure for them
 */
class CategoryMap
{
    public $tree;
    private $catListById; // map categories by id
    private $catListBySlug; // map categories by slug
    private static $instance;
    private $categories;

    /**
     * singleton instance
     * @return \app\Helpers\CategoryMap
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            self::$instance = new $className;
        }
        return self::$instance;
    }

    // ===================== Categories mapping - BEGIN ======================== //

    /**
     * create a mapping of the categories, using a non recursive method
     *
     * @param array $categories : categories from db, as array
     * @return object the categories tree structure
     */
    public function mapCategories($categories)
    {
        $this->tree = array();
        $this->catListById = array();
        $this->catListBySlug = array();
        $this->categories = $categories;

        if ($categories === null) {
            return;
        }
        $this->indexCategories($categories);
        $this->tree = $this->createTreeStructure();
    }

    /**
     * create a mapping of the categories, by Id and by Slug
     *
     * @param array $categoryList : categories from db, as array
     */
    private function indexCategories($categoryList)
    {
        // build an indexed array and map items to the array, using the item id as the key
        foreach($categoryList as $item) {
            $mapItem = new CategoryMapItem($item);
            $this->catListById[$item->id] = $mapItem;
            $this->catListBySlug[$item->slug] = $mapItem;
        }
    }

    /**
     * structure the categories which are stored in $this->catListById, as a tree
     * and set the children Ids and recursive children ids to the items
     *
     * @return App\Helpers\CategoryMapItem a tree structure of the categories
     */
    private function createTreeStructure()
    {
        $rootNodeData = (object)['id' => 0, 'parent_id' => null, 'name' => 'Root', 'slug' => 'root', 'order_index' => 0];
        $tree = new CategoryMapItem($rootNodeData);
        // Loop over hash table
        foreach ($this->catListById as $item) {
            $mappedItem = $this->catListById[$item->id];
            // If the element is not at the root level, add it to its parent array of children.
            // Note this will continue till we have only root level elements left
            if ($mappedItem->parent_id) {
                $parentId = $mappedItem->parent_id;
                $parentItem = $this->catListById[$parentId];
            }
            // If the element is at the root level, directly push to the tree
            else {
                $parentItem = $tree;
            }

            // set the children ids array and counts for all the parents of the element
            $this->addChildToParents($parentItem, $item->id, $tree);
            // add the element to the parent children array
            array_push($parentItem->children, $mappedItem);
        }
        return $tree;
    }

    /**
     * structure the categories which are stored in $this->catListById, as a tree
     *
     * @param number $exceptId if specified, will not include this categoryId and neither it's children and grand children
     * @return App\Helpers\CategoryMapItem the tree structure except for the specified category and it's children and grand children
     */
    private function getTreeStructureExcept($exceptId = 0)
    {
        $rootNodeData = (object)['id' => 0, 'parent_id' => null, 'name' => 'Root', 'slug' => 'root', 'order_index' => 0];
        $tree = new CategoryMapItemBase($rootNodeData);
        $listById = [];
        foreach($this->categories as $item) {
            $mapItem = new CategoryMapItemBase($item);
            $listById[$item->id] = $mapItem;
        }

        // Loop over hash table
        foreach ($listById as $item) {
            if ($exceptId && $item->id === $exceptId) {
                continue;
            }
            $mappedItem = $listById[$item->id];
            if ($exceptId && $this->childHasParent($mappedItem, $exceptId)) {
                continue;
            }
            // If the element is not at the root level, add it to its parent array of children.
            // Note this will continue till we have only root level elements left
            if ($mappedItem->parent_id) {
                $parentId = $mappedItem->parent_id;
                $parentItem = $listById[$parentId];
            }
            // If the element is at the root level, directly push to the tree
            else {
                $parentItem = $tree;
            }

            // add the element to the parent children array
            array_push($parentItem->children, $mappedItem);
        }
        return $tree;
    }

    /**
     * verify if the parent or an ancestor of the given category has the id equal to $parentId
     *
     * @param App\Helpers\CategoryMapItem $category the category for which to verify
     * @param number $parentId the id of the parent to match against
     */
    private function childHasParent($category, $parentId)
    {
        if ($category->parent_id === 0) {
            return false;
        }

        // check if the parent has the id equal to $parentId
        $upperParentId = $this->catListById[$category->parent_id]->parent_id;
        if ($parentId === $category->parent_id) {
            return true;
        }

        // check all grand parents
        while ($upperParentId) {
            if ($upperParentId === $parentId)  {
                return true;
            }
            // bubble up to the upper parent item
            $upperParentId = $this->catListById[$upperParentId]->parent_id;
        }
        return false;
    }

    /**
     * add categoryId to the childrenIds and recursiveChildrenIds of the parent and ancestors
     *
     * @param App\Helpers\CategoryMapItem $parentItem parent item to add category
     * @param number $categoryId category id to add
     * @param App\Helpers\CategoryMapItem $tree tree object which contains the root category
     *
     */
    private function addChildToParents($parentItem, $categoryId, $tree)
    {
        // for the direct parent, add the categoryId to the children and recursive children array
        array_push($parentItem->childrenIds, $categoryId);
        array_push($parentItem->recursiveChildrenIds, $categoryId);
        // increment the parent children and recursive children count
        $parentItem->childrenCount++;
        $parentItem->recursiveChildrenCount++;

        // for ancestors, add the categoryId only to the recursive children array
        $upperParentId = $parentItem->parent_id;
        while ($upperParentId !== null) {
            // set the upper parent to be either the parent or the root node
            $upperParentItem = $upperParentId === 0 ? $tree : $this->catListById[$upperParentId];
            // add the categoryId to the upper parent recursive children array
            array_push($upperParentItem->recursiveChildrenIds, $categoryId);
            $upperParentItem->recursiveChildrenCount++;
            // bubble up to the upper parent item
            $upperParentId = $upperParentItem->parent_id;
        }
    }

    // ===================== Categories mapping - END ======================== //

    /**
     * get categories count
     *
     * @return number categories count
     */
    public function getCategoriesCount()
    {
        return count($this->catListById);
    }

    /**
     * get the category item for the given slug
     *
     * @param string $slug the slug of the category
     * @return App\Helpers\CategoryMapItem the category item if found, otherwise null
     */
    public function getCategoryItemBySlug($slug)
    {
        return isset($this->catListBySlug[$slug]) ? $this->catListBySlug[$slug] : null;
    }

    /**
     * get the category item for the given id
     *
     * @param number $id the id of the category
     * @return App\Helpers\CategoryMapItem the category item if found, otherwise null
     */
    public function getCategoryItemById($id)
    {
        return isset($this->catListById[$id]) ? $this->catListById[$id] : null;
    }

    /**
     * get the tree structure of the categories, except the category (and all it's children and grandchildren) which has the given slug
     *
     * @param string $slug the slug of the category
     * @return App\Helpers\CategoryMapItem the category item if found, otherwise null
     */
    public function getTreeExceptBySlug($slug)
    {
        $this->getTreeExcept($this->catListBySlug[$slug]->id);
    }

    /**
     * get the tree structure of the categories, except the category (and all it's children and grandchildren) which has the given id
     *
     * @param string $slug the slug of the category
     * @return App\Helpers\CategoryMapItem the category item if found, otherwise null
     */
    public function getTreeExcept($id)
    {
        return $this->getTreeStructureExcept($id);
    }

    /**
     * get the direct children of given category id
     *
     * @param number $id the category id for which to get the children
     * @return array direct children of given category
     */
    public function getCategoryChildrenList($id)
    {
        $categoryItem = $this->getCategoryItemById($id);
        if (!$categoryItem || $categoryItem->childrenCount == 0) {
            return null;
        }

        $children = array();
        foreach ($categoryItem->children as $child) {
            $catItem  = $this->catListById[$child->id];
            array_push($children, $catItem);
        }

        return $children;
    }

    /**
     * get parent and ancestors of given category
     *
     * @param number $id the category id for which to get the parent and ancestors
     * @return array list with parent and ancestors
     */
    public function getCategoryParents($id)
    {
        $parentList = array();
        $categoryItem = $this->getCategoryItemById($id);

        $parentLevel = 0;
        while ($categoryItem != null) {
            $parentList[$parentLevel] = $categoryItem;
            $categoryItem = $this->getCategoryItemById($categoryItem->parent_id);
            $parentLevel++;
        }

        return $parentList;
    }

     /**
     * get parent at specified level
     *
     * @param number $id the category id for which to get the parent
     * @param number $level the level of the parent to get
     * @return App\Helpers\CategoryMapItem the parent category at specified level, if found
     */
    public function getParentAtLevel($id, $level)
    {
        $categoryItem = $this->getCategoryItemById($id);
        $parentItem = null;
        while ($categoryItem != null) {
            $categoryItem = $this->getCategoryItemById($categoryItem->parent_id);
            if ($categoryItem && $categoryItem->level == $level) {
                $parentItem = $categoryItem;
                break;
            }
        }

        return $parentItem;
    }

    /**
     * get categories as JSON
     *
     * @return string categories as JSON
     */
    public function getCategoriesAsJson()
    {
        return json_encode($this->catListById);
    }

    /**
     * read categories from a JSON string and create the tree structure
     */
    public function readCategoriesFromJson($jsonContent)
    {
        $categories = json_decode($jsonContent);
        if ($categories) {
            $this->mapCategories($categories);
        }
    }

    /**
     * get the max order index from the children of the given category id and add 1 to it
     *
     * @param number $categoryId the category id for which to calculate next order index
     * @return number the next available order index
     */
    public function getChildrenNextOrderIndex($categoryId)
    {
        $orderIndex = 0;
        $children = $this->getCategoryChildrenList($categoryId);
        if ($children) {
            foreach ($children as &$row) {
                if ($orderIndex < $row->order_index) {
                    $orderIndex = $row->order_index;
                }
            }
            $orderIndex++;
        }
        return $orderIndex;
    }

    /**
     * get category list
     *
     */
    public function getCategoryList()
    {
        return $this->catListById;
    }
}
