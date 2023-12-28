<?php
namespace App\Helpers;
use App\Helpers\CategoryMapItemBase;

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
