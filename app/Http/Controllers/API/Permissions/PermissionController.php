<?php

namespace App\Http\Controllers\API\Permissions;

use App\Enums\HttpCode;
use App\Helpers\ArrayUtils;
use App\Helpers\ExportUtils;
use App\Helpers\Form;
use App\Http\Controllers\API\BaseController;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * class which handles the list / create / update / delete of permissions
 */
class PermissionController extends BaseController
{
    protected $exportFileName = 'permissions';
    protected $translationPrefix = 'permission.';
    protected $model = 'App\Models\Permission';
    private $updateFields; // fields that will be updated on save
    private $selectFields;
    private $userSettings;
    private $filterFields;

    public function __construct()
    {
        $this->updateFields = ['name', 'slug'];
        $this->selectFields = ['id', 'name', 'slug'];
        $this->filterFields = ['search', 'name'];

        // $this->middleware(function ($request, $next) {
        //     $this->userSettings = UserUtils::getUserSetting(auth('sanctum')->user()->id);
        //     return $next($request);
        // });
    }

    /**
     * get permission list
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Http\JsonResponse response which contains the list with permissions
     */
    public function list(Request $request)
    {
        $query = $this->model::select($this->selectFields);
        $query = $this->applyFilters($query, $request);

        $dataCount = $query->count();
        $data = $query->get()->toArray();
        return $this->sendResponse($data, HttpCode::OK, ['totalCount' => $dataCount]);
    }

    /**
     * apply filters from request, to the given query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query query builder
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Database\Eloquent\Builder query with the filters applied
     */
    private function applyFilters($query, $request)
    {
        $filteredQuery = $query;
        foreach ($this->filterFields as $filterKey) {
            if ($request->has($filterKey)) {
                $filterValue = $request->{$filterKey};
                switch ($filterKey) {
                    case 'search':
                        $filteredQuery = $filteredQuery->where('name', 'LIKE', '%' . $filterValue . '%')
                            ->orWhere('slug', 'LIKE', '%' . $filterValue . '%');
                        break;
                    case 'name':
                        $filteredQuery = $filteredQuery->where('name', 'LIKE', '%' . $filterValue . '%');
                        break;
                }
            }
        }
        return $filteredQuery;
    }

    /**
     * store permission to database -> create new entry
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Http\JsonResponse a json response, with the created entry, or error if validation fails
     */
    public function store(Request $request)
    {
        $validator = $this->validateItemRequest($request);
        if ($validator->failed) {
            return $this->sendError([$validator->errors], HttpCode::BadRequest);
        }
        $response = $this->createItem($request);
        return $this->sendResponse($response, HttpCode::Created);
    }

    /**
     * permission get item
     *
     * @param number $id product id
     * @return \Illuminate\Http\JsonResponse a json response, with the entity if found, otherwise will throw a 404 not found
     */
    public function getItem($id)
    {
        $data = $this->getItemForEdit($id);
        return $this->sendResponse($data);
    }

    /**
     * update permission in database
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $id permission id to update
     * @return \Illuminate\Http\JsonResponse a json response with the updated permission or a Bad request with errors, if failed
     */
    public function update(Request $request, $id)
    {
        $validator = $this->validateItemRequest($request, $id);
        if ($validator->failed) {
            return $this->sendError([$validator->errors], HttpCode::BadRequest);
        }
        $response = $this->saveItem($request, $id);
        return $this->sendResponse($response);
    }

    /**
     * Delete permission from db
     *
     * @param number $id permission id
     * @return \Illuminate\Http\Response empty response if entity found and deleted, otherwise 404 not found
     */
    public function delete($id)
    {
        $item = $this->model::find($id);
        if ($item) {
            $item->delete();
            return $this->sendEmptyResponse(HttpCode::NoContent);
        } else {
            return $this->sendEmptyResponse(HttpCode::NotFound);
        }
    }

    /**
     * Delete permissions from db
     *
     * @param \Illuminate\Http\Request $request user request, must contain ids as an array of numbers
     * @return \Illuminate\Http\Response empty response if request is valid, otherwise a bad request status
     */
    public function deleteSelected(Request $request)
    {
        if (!$request->has('ids')) {
            return $this->sendError(['Validation Error'], HttpCode::BadRequest);
        }
        $ids = json_decode($request->ids, JSON_NUMERIC_CHECK);
        $ids = ArrayUtils::transformToPositiveIntegers($ids);
        if (!$ids) {
            return $this->sendError(['Validation Error'], HttpCode::BadRequest);
        }
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
     * export selected data
     *
     * @param \Illuminate\Http\Request $request user request, must contain: export_format
     * @return \Illuminate\Http\Response containing the exported data or a bad request if request not valid
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
                $pdf = Pdf::loadView($this->exportPath . '.export-table-permissions', compact('data'));
                // download pdf file
                return $pdf->download($fileName . '.pdf');
                break;
        }
    }

    /**
     * get permission item for edit; throws a 404 not found exception if item not found in database
     *
     * @param number $itemId permission id
     * @return \app\Models\Permission permission model
     */
    private function getItemForEdit($itemId)
    {
        $data = $this->model::select($this->selectFields)
            ->findOrFail($itemId);

        return $data;
    }

    /**
     * get data for export from db, for the given request
     *
     * @param \Illuminate\Http\Request $request user request
     * @return array a list with all permissions
     */
    private function getExportData(Request $request)
    {
        $data = $this->model::select($this->selectFields)->get();
        return $data;
    }

    /**
     * get the csv content for the given data
     *
     * @param array $data array of permission models
     * @return string csv content for provided data
     */
    private function getCsvContent($data)
    {
        $columnList = [__('tables.Id'), __('tables.Name'), __('tables.Slug')];

        return ExportUtils::getCsvContent($data, $columnList, $this->selectFields);
    }

    /** functions used to create / update permission - BEGIN */

    /**
     * validate item request before create / save
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $id permission id
     * @return object an object with the fields { failed, errors }
     */
    private function validateItemRequest(Request $request, $id = null)
    {
        $uniqueName = 'unique:permissions,name';
        $uniqueSlug = 'unique:permissions,slug';
        if ($id) {
            $uniqueName .= ',' . $id;
            $uniqueSlug .= ',' . $id;
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'slug' => ['required', 'string', 'max:255', $uniqueSlug],
        ];

        return $this->validateRules($request, $rules);
    }

    /**
     * save new permission in database, from provided request
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \app\Models\Permission the created entity
     */
    private function createItem(Request $request)
    {
        $item = new $this->model();
        Form::updateModelFromRequest($request, $item, $this->updateFields);
        $item->save();
        return $item;
    }

    /**
     * update permission in database, from provided request
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $itemId id of the permission to save
     * @return \app\Models\Permission the updated entity
     */
    private function saveItem(Request $request, $itemId)
    {
        $item = $this->model::findOrFail($itemId);
        Form::updateModelFromRequest($request, $item, $this->updateFields);
        $item->save();
        return $item;
    }

    /** functions used to create / update permission - END */
}
