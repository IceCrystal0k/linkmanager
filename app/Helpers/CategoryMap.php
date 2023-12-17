<?php
namespace App\Helpers;
use App\Helpers\ArrayUtils;

class CategoryMap
{
    public $tree;
    public $arrCategoryIndex; // map for categoryID
    public $catListById; // map categories by id
    public $catListBySlug; // map categories by slug
    private static $instance;
    private $categories;

    // singleton
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
     * @param {array} $categories : categories from db, as array; they must be ordered by parent_id,order_index
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
     * structure the categories which are stored in $this->catListById,  as a tree
     *
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
     * verify if the parent or the ancestors of the given category the id equal to $parentId
     *
     * @param CategoryMapItem $category the category for which to verify
     * @param string $parentId the id of the parent to match against
     */
    private function childHasParent($category, $parentId)
    {
        if ($category->parent_id === 0) {
            return false;
        }

        $upperParentId = $this->catListById[$category->parent_id]->parent_id;
        if ($parentId === $category->parent_id) {
            return true;
        }
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
     * add categoryId to parent and ancestors
     *
     * @param CategoryMapItem $parentItem parent item to add category
     * @param number $categoryId category id to add
     * @param CategoryMapItem $tree tree object which contains the root category
     *
     */
    private function addChildToParents($parentItem, $categoryId, $tree)
    {
        // for direct parent, add the categoryId to the children and recursive children array
        // add the element id to the parent children ids and recursive parent children ids array
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

    public function getCategoryItemBySlug($slug)
    {
        return isset($this->catListBySlug[$slug]) ? $this->catListBySlug[$slug] : null;
    }

    public function getCategoryItemById($id)
    {
        return isset($this->catListById[$id]) ? $this->catListById[$id] : null;
    }

    public function getTreeExceptBySlug($slug)
    {
        $this->getTreeExcept($this->catListBySlug[$slug]->id);
    }

    // returns the tree structure except the given category id and its children
    public function getTreeExcept($id)
    {
        return $this->getTreeStructureExcept($id);
    }

    // returns the direct children of given category
    public function getCategoryChildrenList($id)
    {
        if (count($this->arrCategoryIndex) == 0) {
            return null;
        }

        if (!isset($this->arrCategoryIndex[$id])) {
            return null;
        }

        $row = $this->tree[$this->arrCategoryIndex[$id]]; // get category Item for the given category Id

        if ($row->DirectChildrenCount == 0) {
            return null;
        }

        $arrTreeList = array();
        $arrTreeIds = explode(',', $row->DirectChildrenIds); // get children of the searched category
        foreach ($arrTreeIds as $catId) {
            array_push($arrTreeList, $this->tree[$this->arrCategoryIndex[$catId]]);
        }

        return $arrTreeList;
    }

    // returns all children of given category, in a tree order
    // if skipId specified, will not include the category with that id and all it's children
    // if $includeCategory, it will include the category with $id
    public function getCategoryTreeRecursive($id, $skipId = null, $includeCategory = false)
    {
        $arrTreeList = array();
        if (count($this->tree) === 0) {
            return $arrTreeList;
        }
        // dd($this->treeCategories);
        if ($this->tree) {
            if ($includeCategory) {
                $row = $this->tree[$this->arrCategoryIndex[$id]]; // get category Item for the given category Id
                array_push($arrTreeList, $row);
            }
        }

        $this->GetChildrenRecursive($id, $arrTreeList, $skipId);

        return $arrTreeList;
    }

    // returns all children of given category, in a tree order
    public function getChildrenRecursive($id, &$arrTreeList, $skipId = null)
    {
        $row = $this->treeCategories[$this->arrCategoryIndex[$id]]; // get category Item for the given category Id
        if ($row->DirectChildrenCount == 0) {
            return;
        }

        $arrTreeIds = explode(',', $row->DirectChildrenIds); // get children of the searched category

        foreach ($arrTreeIds as $catId) {
            $row = $this->treeCategories[$this->arrCategoryIndex[$catId]];
            $row->parentRef = null;
            if ($skipId !== null && $row->id === $skipId) {
                continue;
            }

            array_push($arrTreeList, $row);
            if ($row->DirectChildrenCount > 0) {
                $this->GetChildrenRecursive($row->id, $arrTreeList, $skipId);
            }

        }
    }

    public function getCategoryParents($id)
    {
        $arrItems = array();
        $itemsCount = 0;

        $categoryItem = $this->getCategoryItemById($id);
        while ($categoryItem != null) {
            $arrItems[$itemsCount] = $categoryItem;
            $categoryItem = $categoryItem->parentRef;
            $itemsCount++;
        }

        return $arrItems;
    }

    public function getParentAtLevel($id, $level)
    {
        $categoryItem = $this->getCategoryItemById($id);
        $parentItem = null;
        while ($categoryItem != null) {
            $categoryItem = $categoryItem->parentRef;
            if ($categoryItem->level == $level) {
                $parentItem = $categoryItem;
                break;
            }
        }

        return $parentItem;
    }

    public function getCategoriesAsJson()
    {
        return json_encode($this->catListById);
    }

    public function readCategoriesFromJson($jsonContent)
    {
        $arrCategories = json_decode($jsonContent);
        $this->treeCategories = array();
        foreach ($arrCategories as $category) {
            $newCat = new CategoryMapItem($category);
            $this->copyObjectAttributes($category, $newCat);
            array_push($this->treeCategories, $newCat);

            // rebuild indexes
            $this->arrCategoryIndex[$category->id] = $category;
            $this->catListBySlug[$category->slug] = $category;
        }
    }


    private function copyObjectAttributes(&$objSrc, &$objDest)
    {
        foreach (get_object_vars($objSrc) as $key => $value) {
            $objDest->{$key} = $value;
        }
    }

    // get the max order index from the children of the given category id and add 1 to it
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
}

class CategoryMapItemBase
{
    // attributes from database
    public $id;
    public $parent_id;
    public $name;
    public $slug;
    public $order_index;
    public $children;

    public function __construct($category)
    {
        $this->id = $category->id;
        $this->parent_id = $category->parent_id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->order_index = $category->order_index;

        $this->children = array();
    }
}

class CategoryMapItem extends CategoryMapItemBase
{
    // attributes for faster access - camel cased, as opposed to the ones from database
    public $childrenCount;
    public $childrenIds;
    public $recursiveChildrenCount;
    public $recursiveChildrenIds;
    public $level;

    public function __construct($category)
    {
        parent::__construct($category);

        $this->childrenCount = 0;
        $this->childrenIds = array();
        $this->recursiveChildrenCount = 0;
        $this->recursiveChildrenIds = array();
        $this->level = 0;
    }
}
