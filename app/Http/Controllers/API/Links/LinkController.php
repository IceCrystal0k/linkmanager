<?php

namespace App\Http\Controllers\API\Links;

use App\Enums\HttpCode;
use App\Helpers\ArrayUtils;
use App\Helpers\ExportUtils;
use App\Helpers\Form;
use App\Helpers\UserUtils;
use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;


/**
 * class which handles the list / create / update / delete of links
 */
class LinkController extends BaseController
{
    protected $exportFileName = 'links';
    protected $translationPrefix = 'links.';
    protected $model = 'App\Models\Link';
    private $updateFields; // fields that will be updated on save
    private $selectFields;
    private $userSettings;
    private $filterFields;
    private $exportFields; // fields that are selected for export

    public function __construct()
    {
        $this->updateFields = ['category_id', 'name', 'url', 'description', 'comment', 'rating', 'visits', 'last_verified_at', 'verification_status', 'username', 'password', 'auth_info'];
        $this->selectFields = ['id', 'category_id', 'name', 'url', 'description', 'comment', 'rating', 'visits', 'last_verified_at', 'verification_status', 'username', 'password', 'auth_info', 'created_at', 'updated_at'];
        $this->filterFields = ['search', 'name', 'category_id', 'url', 'description', 'comment', 'rating', 'visits', 'verification_status', 'created_at'];
        $this->exportFields = ['id', 'category_name', 'name', 'url', 'rating', 'visits', 'status_name', 'created_at'];

       $this->middleware(function ($request, $next) {
            $this->userSettings = UserUtils::getUserSetting(auth('sanctum')->user()->id);
            return $next($request);
        });
    }

    /**
     * get link list
     * @param \Illuminate\Http\Request $request user request
     * @return array list with links
     */
    public function list(Request $request) {
        $query = $this->model::select($this->selectFields);
        $query = $this->applyFilters($query, $request);

        $data = $query->get()->toArray();
        return $data;
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
                            ->orWhere('url', 'LIKE', '%' . $filterValue . '%')
                            ->orWhere('description', 'LIKE', '%' . $filterValue . '%')
                            ->orWhere('comment', 'LIKE', '%' . $filterValue . '%');
                        break;
                    case 'name':
                    case 'url':
                    case 'description':
                    case 'comment':
                        $filteredQuery = $filteredQuery->where($filterKey, 'LIKE', '%' . $filterValue . '%');
                        break;
                    case 'category_id':
                    case 'verification_status':
                        $filteredQuery = $filteredQuery->where($filterKey, $filterValue);
                        break;
                    case 'rating':
                    case 'visits':
                    case 'created_at':
                        $filterData = json_decode($filterValue);
                        if (!$filterData || gettype($filterData) !== 'object') {
                            break;
                        }
                        if (!isset($filterData->min)) {
                            $filterData->min = null;
                        }
                        if (!isset($filterData->max)) {
                            $filterData->max = null;
                        }
                        if ($filterKey === 'created_at') {
                            // check the dates to be compatible with mysql, or make them timestamps
                            // print_r($filterData);
                        }
                        if ($filterData->min && $filterData->max) {
                            $filteredQuery = $filteredQuery->whereBetween($filterKey, [$filterData->min, $filterData->max]);
                        }
                        else if ($filterData->min) {
                            $filteredQuery = $filteredQuery->where($filterKey, '>=', $filterData->min);
                        }
                        else if ($filterData->max) {
                            $filteredQuery = $filteredQuery->where($filterKey, '<=', $filterData->max);
                        }
                        break;
                    break;
                }
            }
        }
        return $filteredQuery;
    }


    /**
     * store link to database -> create new entry
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
     * link get item
     *
     * @param number $id product id
     * @return \Illuminate\Http\JsonResponse a json response, which specifies if the entity was created or not
     */
    public function getItem($id)
    {
        $data = $this->getItemForEdit($id);
        return $this->sendResponse($data, null);
    }

    /**
     * update link in database
     * @param {object} $request http request
     * @param {number} $id link id to update
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
    }

    /**
     * Delete link from db
     *
     * @param number $id link id
     * @return \Illuminate\Http\Response an empty response, if the entity was deleted or 404 not found if the entity was not found
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
     * Delete links from db
     *
     * @param \Illuminate\Http\Request $request user request, must contain ids as an array of numbers
     * @return object either empty response if all good or a validation error
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
                $pdf = PDF::loadView($this->exportPath . '.export-table-links', compact('data'));
                // download pdf file
                return $pdf->download($fileName . '.pdf');
                break;
        }
    }

    /**
     * get link item for edit
     * @param {number} $itemId link id
     * @return {object} link model
     */
    private function getItemForEdit($itemId)
    {
        $data = $this->model::select($this->selectFields)
            ->findOrFail($itemId);

        return $data;
    }

    /**
     * get data for export from db, for the given request
     * @param {object} $request http request
     * @return {array} of link models
     */
    private function getExportData(Request $request)
    {
        $exportDateRange = $request->has('export_daterange') ? $request->export_daterange : null;
        $exportStatus = $request->has('export_status') ? $request->export_status : null;

        $query = $this->model::leftJoin('categories', 'categories.id', '=', 'links.category_id')
            ->select(['links.id', 'categories.name as category_name', 'links.name', 'url',
                'rating', 'visits', 'verification_status', 'links.created_at']);

        if ($exportDateRange) {
            list($dateStart, $dateEnd) = explode(' - ', $exportDateRange);
            try {
                $dateStart = \Carbon\Carbon::parse($dateStart);
                $dateEnd = \Carbon\Carbon::parse($dateEnd);
                $query->whereBetween('links.created_at', [$dateStart, $dateEnd]);
            } catch (\Exception $e) {
                // show some error
            }
        }
        if ($exportStatus) {
            $statusList = is_array($exportStatus) ? $exportStatus : [];
            $statusListFiltered = [];
            foreach ($statusList as $val) {
                if (is_numeric($val)) {
                    array_push($statusListFiltered, (int) $val);
                }
            }
            if ($statusListFiltered && count($statusListFiltered) > 0) {
                $query->whereIn('verification_status', $statusListFiltered);
            }
        }

        $data = $query->get();
        foreach ($data as &$row) {
            // created_at format can't be changed, so add a new date attribute
            $row->created_date = date($this->userSettings->date_format_php, strtotime($row->created_at));
        }

        return $data;
    }

    /**
     * get the csv content for the given data
     * @param {array} $data array of role models
     * @return {string} csv content for provided data
     */
    private function getCsvContent($data)
    {
        $columnList = [__('tables.Id'), __('tables.Category'), __('tables.Name'), __('tables.Url'), __('tables.Rating'),
        __('tables.Visits'), __('tables.Status'), __('tables.CreatedAt')];

        return ExportUtils::getCsvContent($data, $columnList, $this->exportFields);
    }

    /** functions used to create / update role - BEGIN */

    /**
     * validate item request before create / save
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $id permission id
     * @return object an object with the fields { failed, errors }
     */
    private function validateItemRequest(Request $request, $id = null)
    {
        $uniqueName = 'unique:links,name';
        if ($id) {
            $uniqueName .= ',' . $id;
        }

        $rules = [
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'url' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'numeric', 'exists:categories,id'],
            'description' => ['string', 'max:255'],
            'comment' => ['string', 'max:16383'], // utf8mb4 requires 4 bytes per char
            'rating' => ['numeric', 'min:1', 'max:5'],
            'username' => ['string', 'max:255'],
            'password' => ['string', 'max:255'],
            'auth_info' => ['string', 'max:255']
        ];

        return $this->validateRules($request, $rules);
    }

    /**
     * save new role in database, from provided request
     * @param {object} $request http request
     * @return {number} created id
     */
    private function createItem(Request $request)
    {
        $item = new $this->model();
        Form::updateModelFromRequest($request, $item, $this->updateFields);
        $item->save();
        return $item;
    }

    /**
     * update link in database, from provided request
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $itemId id of the link to save
     * @return object the updated entity
     */
    private function saveItem(Request $request, $itemId)
    {
        $item = $this->model::findOrFail($itemId);
        Form::updateModelFromRequest($request, $item, $this->updateFields);
        $item->save();
        return $item;
    }

    /** functions used to create / update role - END */

}
