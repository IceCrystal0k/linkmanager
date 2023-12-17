<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    public $timestamps = false;

    public static function updateCategoryChildrenOrder($parentId, $categoryId = 0, $orderIndex = 0)
    {
        $table = 'categories';
        $sqlQuery = "SET @a = {$orderIndex}; UPDATE {$table} SET order_index = @a:=@a+1 WHERE parent_id = {$parentId} AND order_index >= {$orderIndex} AND id <> {$categoryId} ORDER BY order_index";
        DB::unprepared($sqlQuery);

        $sqlQuery = "SET @a = 0; UPDATE {$table} SET order_index = @a:=@a+1 WHERE parent_id = {$parentId} AND order_index < {$orderIndex} AND id <> {$categoryId} ORDER BY order_index";
        DB::unprepared($sqlQuery);
    }
}