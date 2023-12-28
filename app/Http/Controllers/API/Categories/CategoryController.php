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
     * get category list
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Http\JsonResponse response which contains the list with categories
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
        $dataCount = $categoryMap->getCategoriesCount();
        return $this->sendResponse($data, HttpCode::OK, ['totalCount' => $dataCount]);
    }

    /**
     * handle ajax actions
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Http\JsonResponse response which contains the result of the action
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
            return $this->sendError([$validator->errors], HttpCode::BadRequest);
        }
        $item = $this->createItem($request);
        return $this->sendResponse($item, HttpCode::Created);
    }

    /**
     * category get item
     *
     * @param number $id category id
     * @return \Illuminate\Http\JsonResponse a json response, with the entity if found, otherwise throws a 404 not found
     */
    public function getItem($id)
    {
        $data = $this->getItemForEdit($id);
        return $this->sendResponse($data);
        return $data;
    }

    /**
     * update category in database
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $id category id to update
     * @return \Illuminate\Http\JsonResponse a json response with the updated category or a Bad request with errors, if failed
     */
    public function update(Request $request, $id)
    {
        $validator = $this->validateItemRequest($request, $id);
        if ($validator->failed) {
            return $this->sendError([$validator->errors], HttpCode::BadRequest);
        }
        $response = $this->saveItem($request, $id);
        return $this->sendResponse($response);
        // $this->saveSeo($request, $id);
    }

    /**
     * delete category and all children and grand children of the category
     *
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
     * delete selected categories and all their children and grand children from db
     *
     * @param \Illuminate\Http\Request $request user request, must contain ids as an array of numbers
     * @return \Illuminate\Http\Response empty response if request is valid, otherwise a bad request status
     */
    public function deleteSelected(Request $request)
    {
        if (!$request->has('ids')) {
            return $this->sendError(['Validation Error.'], HttpCode::BadRequest);
        }
        $ids = json_decode($request->ids, JSON_NUMERIC_CHECK);
        $ids = ArrayUtils::transformToPositiveIntegers($ids);
        if (!$ids) {
            return $this->sendError(['Validation Error.'], HttpCode::BadRequest);
        }
        // delete all children and grand children
        $this->deleteChildrenRecursive($ids);
        $this->model::whereIn('id', $ids)->delete();
        return $this->sendEmptyResponse(HttpCode::NoContent);
    }

     /**
     * validate export params
     *
     * @param \Illuminate\Http\Request $request user request, must contain: export_format
     * @return boolean true if parameters are valid, false otherwise
     */
    private function validateExport(Request $request)
    {
        if (!$request->has('export_format')) {
            return false;
        }
        return true;
    }

    /**
     * export all categories
     *
     * @param \Illuminate\Http\Request $request http request, must contain: export_format
     * @return \Illuminate\Http\Response containing the exported data
     */
    public function export(Request $request)
    {
        if (!$this->validateExport($request)) {
            return $this->sendError(['Validation Error.'], HttpCode::BadRequest);
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
     *
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
     * verify if the specified categoryId can be moved in the new parentId
     * if the new parent (from dataSave) is the same as categoryId or is a child of categoryId, return error
     *
     * @param \app\Helpers\CategoryMapItem $category category which is moved
     * @param number $newParentId id of the category where to move
     * @return object response containing { valid, error }
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
     * get category model for edit; throws a 404 not found exception if item not found in database
     *
     * @param number $itemId category id of the category for which to get the data
     * @return \app\Models\Category category model
     */
    private function getItemForEdit($itemId)
    {
        $data = $this->model::select($this->selectFields)->findOrFail($itemId);
        return $data;
    }

    /**
     * get data for export from db, for the given request
     *
     * @param \Illuminate\Http\Request $request user request
     * @return array a list with all categories
     */
    private function getExportData(Request $request)
    {
        $data = $this->model::select($this->exportFields)->get();
        return $data;
    }

    /**
     * get the csv content for the given data
     *
     * @param array $data array of page category models
     * @return string csv content for provided data
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
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \app\Models\Category the created entity
     */
    private function createItem(Request $request)
    {
        $item = new $this->model();
        Form::updateModelFromRequest($request, $item, $this->updateFields);
        if (!$item->parent_id) {
            $item->parent_id = 0;
        }

        $item->order_index = $this->getNextOrderIndex($item->parent_id);
        $item->save();
        return $item;
    }

    /**
     * update category in database, from provided request
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $itemId id of the category to save
     * @return \app\Models\Category the updated entity
     */
    private function saveItem(Request $request, $itemId)
    {
        $item = $this->model::findOrFail($itemId);
        Form::updateModelFromRequest($request, $item, $this->updateFields);
        $item->save();
        return $item;
    }

    /**
     * get category order index, for newly created category
     *
     * @param number $parentId id of the parent category
     * @return number order index
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
     *
     * @param array $ids category ids for which to get the children
     * @return array a list with the unique recursive children ids
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
