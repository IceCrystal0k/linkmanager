<?php

namespace App\Http\Controllers\API\Categories;

use App\Enums\HttpCode;
use App\Helpers\ArrayUtils;
use App\Helpers\CategoryUtils;
use App\Helpers\ExportUtils;
use App\Helpers\Form;
use App\Http\Controllers\API\BaseController;
use App\Models\Seo;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * class which handles the list / create / update / delete of categories
 */
class CategoryController extends BaseController
{
    protected $exportFileName = 'categories';
    protected $translationPrefix = 'category.';
    protected $model = 'App\Models\Category';
    private $updateFields; // fields that will be updated on save
    private $selectFields; // fields that will be updated on save
    private $filterFields;
    private $exportFields; // fields that are selected for export

    public function __construct()
    {
        $this->updateFields = ['name', 'parent_id', 'title', 'slug', 'content'];
        $this->selectFields = ['id', 'name', 'slug'];
        $this->exportFields = ['id', 'parent_id', 'name', 'order_index'];
    }

    /**
     * category list index
     * @return {view} list view
     */
    public function list(Request $request)
    {
        $categoryUtils = new CategoryUtils();
        $categoryMap = $categoryUtils->getCategoryMap();
        // $data = $categoryMap->tree;
        $exceptCategoryId = 0;
        if ($request->has('exceptCategoryId')) {
            $exceptCategoryId = (int)$request->exceptCategoryId;
        }
        $data = $categoryMap->getTreeExcept($exceptCategoryId);
        return $this->sendResponse($data, '');
    }

    /**
     * ajax actions
     * @param {object} $request http request
     * @return {string} json with response data
     */
    public function actions(Request $request)
    {
        switch ($request->ajaxAction) {
            case 'getNodeChildren':return $this->getTreeData();
                break;
            case 'move':return $this->moveCategoryItem($request);
                break;
        }
    }

    /**
     * store category to database -> create new entry
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Http\JsonResponse a json response, which specifies if the entity was created or not
     */
    public function store(Request $request)
    {
        $validator = $this->validateItemRequest($request);
        if ($validator->failed) {
            return $this->sendError('Validation Error.', $validator->errors, HttpCode::BadRequest);
        }
        $item = $this->createItem($request);
        return $this->sendResponse($item, 'Link created successfully', HttpCode::Created);
    }


    /**
     * update category in database
     * @param {object} $request http request
     * @param {number} $id category id to update
     * @return {view} edit view
     */
    public function update(Request $request, $id)
    {
        $validator = $this->validateItemRequest($request, $id);
        if ($validator->failed) {
            return $this->sendError('Validation Error.', $validator->errors, HttpCode::BadRequest);
        }
        $response = $this->saveItem($request, $id);
        return $this->sendResponse($response, 'Link updated successfully.');
        // $this->saveSeo($request, $id);
    }

    /**
     * delete category and all children and grand children of the category
     * @param number $id category id
     * @return \Illuminate\Http\Response an empty response, which specifies if the entity was deleted or 404 not found if the entity was not found
     */
    public function delete($id)
    {
        $item = $this->model::findOrFail($id);
        if ($item) {
            $this->deleteChildrenRecursive([$id]);
            $item->delete();
            return $this->sendEmptyResponse(HttpCode::NoContent);
        }
    }

    /**
     * delete selected categories and references from db
     * @param {object} $request http request
     * @return {view} list view
     */
    public function deleteSelected(Request $request)
    {
        if (!$request->has('ids')) {
            return $this->sendError('Validation Error.', null, HttpCode::BadRequest);
        }
        $ids = json_decode($request->ids, JSON_NUMERIC_CHECK);
        $ids = ArrayUtils::transformToPositiveIntegers($ids);
        if (!$ids) {
            return $this->sendError('Validation Error.', null, HttpCode::BadRequest);
        }
        // delete all children and grand children
        $this->deleteChildrenRecursive($ids);
        $this->model::whereIn('id', $ids)->delete();
        return $this->sendEmptyResponse(HttpCode::NoContent);
    }

     /**
     * validate export params
     * @param \Illuminate\Http\Request $request user request, must contain: export_format
     */
    private function validateExport(Request $request)
    {
        if (!$request->has('export_format')) {
            return false;
        }
        return true;
    }

    /**
     * export all permissions
     *
     * @param \Illuminate\Http\Request $request http request, must contain: export_format
     * @return \Illuminate\Http\Response containing the exported data
     */
    public function export(Request $request)
    {
        if (!$this->validateExport($request)) {
            return $this->sendError('Validation Error.', null, HttpCode::BadRequest);
        }
        $data = $this->getExportData($request);
        $fileName = $this->exportFileName . '-' . date('Y-m-d');
        $exportFormat = $request->export_format;

        switch ($exportFormat) {
            case 'csv':
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '.csv"',
                ];
                $csvContent = $this->getCsvContent($data);
                return response($csvContent)->withHeaders($headers);
                break;
            default:
                $pdf = PDF::loadView($this->exportPath . '.export-table-categories', compact('data'));
                // download pdf file
                return $pdf->download($fileName . '.pdf');
                break;
        }
    }

    /**
     * verify that the specified categoryId and the new parentId exists in the database
     * @param number $categoryId id of the category which is moved
     * @param number $newParentId id of the category where to move
     * @param object $categoryMap an object of type CategoryMap
     * @return object response containing { valid, error }
     */
    private function verifyDataExists($categoryId, $newParentId, $categoryMap)
    {
        $response = (object) ['valid' => false, 'error' => ''];

        if (!$categoryId) {
            $response->error = __($this->translationPrefix . 'MoveErrorInvalidCategory');
            return $response;
        }

        $category = $categoryMap->GetCategoryItemById($categoryId);
        // verify that the category which is being udpated still exists
        if (!$category) {
            $response->error = __($this->translationPrefix . 'MoveErrorSourceCategoryNotFound', ['id' => $categoryId]);
            return $response;
        }

        // verify that the the new parent exists in database
        if ($newParentId) {
            $newParentCategory = $categoryMap->GetCategoryItemById($newParentId);
            if (!$newParentCategory) {
                $response->error = __($this->translationPrefix . 'MoveErrorDestinationCategoryNotFound', ['id' => $newParentId]);
                return $response;
            }
        }

        $response->valid = true;
        return $response;
    }

    /**
     * verify if the specified categoryId can be moved in the parentId
     * if the new parent (from dataSave) is the same as categoryId or is a child of categoryId, return error
     * @param object $category category which is moved, must be a CategoryMapItem object
     * @param {object} $newParentId id of the category where to move
     * @return {object} response containing { status, message }
     */
    private function verifyMoveDataIntegrity($category, $newParentId)
    {
        $response = (object) ['valid' => false, 'error' => ''];

        // if the new parent id is 0 or if the new parent is the same as the current parent, the move is allowed
        if ($newParentId === 0 || $newParentId === $category->parent_id) {
            $response->valid = true;
            return $response;
        }

        // check if the the new parent is the same as category id
        $canUpdateCategory = $newParentId !== $category->id;
        // check that the new parent is not a child of the moved category
        if ($canUpdateCategory && !empty($category->recursiveChildrenIds)) {
            if (in_array($newParentId, $category->recursiveChildrenIds)) {
                $canUpdateCategory = false;
            }
        }
        if (!$canUpdateCategory) {
            $response->error = __($this->translationPrefix . 'MoveErrorWithinChild', ['id' => $category->id, 'parentId' => $newParentId]);
            return $response;
        }

        $response->valid = true;
        return $response;
    }

    /**
     * category get item
     * @param {number} $id category id
     * @return {view} edit view
     */
    public function getItem($id)
    {
        $data = $this->getItemForEdit($id);
        return $this->sendResponse($data, null);
        return $data;
    }

    /**
     * get category model from db
     * @param {number} $itemId category id of the category for which to get the data
     * @return {object} category model
     */
    private function getItemForEdit($itemId)
    {
        $data = $this->model::select($this->selectFields)->findOrFail($itemId);
        return $data;
    }

    /**
     * get data for export from db, for the given request
     * @param {object} $request http request
     * @return {array} of page text models
     */
    private function getExportData(Request $request)
    {
        // selecting PDF view
        $data = $this->model::select($this->exportFields)
            ->get();

        return $data;
    }

    /**
     * get the csv content for the given data
     * @param {array} $data array of page text models
     * @return {string} csv content for provided data
     */
    private function getCsvContent($data)
    {
        $fieldList = ['id', 'parent_id', 'name', 'order_index'];
        $columnList = [__('tables.Id'), __('tables.Parent'), __('tables.Name'), __('tables.OrderIndex')];

        return ExportUtils::getCsvContent($data, $columnList, $fieldList);
    }

    /** functions used to create / update category - BEGIN */

    /**
     * validate item request before create / save
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $id category id
     * @return object an object with the fields { failed, errors }
     */
    private function validateItemRequest(Request $request, $id = null)
    {
        $uniqueName = 'unique:categories,name';
        $uniqueSlug = 'unique:categories,slug';
        if ($id) {
            $uniqueName .= ',' . $id;
            $uniqueSlug .= ',' . $id;
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'slug' => ['required', 'string', 'max:255', $uniqueSlug],
            'parent_id' => ['required', 'numeric']
        ];



        // when creating a new category, validate that the parent id is either 0 or is an id that exists in the database
        if (!$id) {
            $customValidation = function($request, $validator) {
                $this->validateParentData($request);
            };
        }
        else {
            // when editing, validate that request is valid and that parent id is not the same as the moved category or a child of the moved category
            $customValidation = function($request, $validator) use ($id) {
                $validation = $this->validateCategoryUpdate($request, $id);
                if (!$validation->valid) {
                    $validator->errors()->add('parent_id', $validation->error);
                }
            };
        }

        return $this->validateRules($request, $rules, $customValidation);
    }

    /**
     * validate request for create, so that the parent, if not zero, then it should exists in the database
     * if the parent is not zero and doesn't exist in the database, a 404 error will be thrown (the rest of the code will not be executed)
     *
     * @param \Illuminate\Http\Request $request user request
     */
    private function validateParentData(Request $request)
    {
        $parentId = $request->has('parent_id') && $request->parent_id ? $request->parent_id : 0;
        if ($parentId !== 0) {
            $this->model::select(['id'])->findOrFail($parentId);
        }
    }

    /**
     * validate request for edit, so that the parent won't be the same category or a child category
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $id category id
     * @return object an object with the fields { failed, errors }
     */
    private function validateCategoryUpdate(Request $request, $id)
    {
        $catUtils = new CategoryUtils();
        $categoryMap = $catUtils->getCategoryMap();

        // if parent_id is not specified, add it to the request
        $parentId = $request->has('parent_id') && $request->parent_id ? (int)$request->parent_id : 0;
        $validator = $this->verifyDataExists($id, $parentId, $categoryMap);
        if (!$validator->valid) {
            return $validator;
        }
        $category = $categoryMap->GetCategoryItemById($id);
        return $this->verifyMoveDataIntegrity($category, $parentId);
    }

    /**
     * save new category in database, from provided request
     * @param {object} $request http request
     * @return {number} created id
     */
    private function createItem(Request $request)
    {
        $category = new $this->model();
        Form::updateModelFromRequest($request, $category, $this->updateFields);
        if (!$category->parent_id) {
            $category->parent_id = 0;
        }

        $category->order_index = $this->getNextOrderIndex($category->parent_id);
        $category->save();
        return $category->id;
    }

    /**
     * update category in database, from provided request
     * @param {object} $request http request
     * @param {number} $itemId id of the category to save
     */
    private function saveItem(Request $request, $itemId)
    {
        $category = $this->model::findOrFail($itemId);
        Form::updateModelFromRequest($request, $category, $this->updateFields);
        $category->save();
    }

    /**
     * get category order index, for newly created category
     * @param {number} $parentId id of the parent category
     * @return {number} order index
     */
    private function getNextOrderIndex($parentId)
    {
        $orderIndex = $this->model::where('parent_id', $parentId)->max('order_index');
        if (!$orderIndex) {
            $orderIndex = 1;
        } else {
            $orderIndex++;
        }
        return $orderIndex;
    }

    /**
     * delete all children and grand children of the specified categories ids
     *
     * @param array $ids ids to delete
     *
     */
    private function deleteChildrenRecursive($ids)
    {
        $childrenIds = $this->getCategoriesChildrenRecursive($ids);
        if (count($childrenIds) > 0) {
            $this->model::whereIn('id', $childrenIds)->delete();
        }
    }

    /**
     * get a list with all the recursive children ids of the given category ids
     * @param {array} $ids category ids for which to get the children
     * @return {array} a list with the unique recursive children ids
     */
    private function getCategoriesChildrenRecursive($ids)
    {
        $catUtils = new CategoryUtils();
        $categoryMap = $catUtils->getCategoryMap();
        $childrenIds = [];
        foreach ($ids as $id) {
            $category = $categoryMap->getCategoryItemById($id);
            // check if category has children
            if (!empty($category->recursiveChildrenIds)) {
                $childrenIds = array_merge($childrenIds, $category->recursiveChildrenIds);
            }
        }
        if (count($childrenIds) > 0) {
            return array_unique($childrenIds);
        } else {
            return $childrenIds;
        }
    }

    /** functions used to create / update category - END */
}
