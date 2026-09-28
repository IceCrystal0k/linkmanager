<?php
namespace App\Helpers;

class CategoryMapItemBase
{
    // attributes from database
    public $id;
    public $module_id;
    public $parent_id;
    public $name;
    public $slug;
    public $order_index;
    public $children;

    public function __construct($category)
    {
        $this->id = $category->id;
        $this->module_id = $category->module_id;
        $this->parent_id = $category->parent_id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->order_index = $category->order_index;

        $this->children = array();
    }
}
