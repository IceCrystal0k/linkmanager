<?php

namespace App\Http\Controllers\API\Users;

use App\Enums\HttpCode;
use App\Helpers\ArrayUtils;
use App\Helpers\ExportUtils;
use App\Http\Controllers\API\BaseController;
use App\Models\Permission;
use App\Models\User;
use App\Models\UsersPermission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * class which handles the list / update of user permissions
 */
class UserPermissionsController extends BaseController
{
    protected $translationPrefix = 'permission.';
    protected $model = 'App\Models\UsersPermission';
    private $selectFields;

    public function __construct()
    {
        $this->selectFields = ['id', 'name', 'slug', 'active'];
         // $this->middleware(function ($request, $next) {
        //     $this->userSettings = UserUtils::getUserSetting(auth('sanctum')->user()->id);
        //     return $next($request);
        // });
    }

    /**
     * get a list with all the permissions and the selected values for the specified user
     *
     * @param number $userId user for which to get permissions
     * @return \Illuminate\Http\JsonResponse response which contains the list with permissions  [{ id, name, slug, active }]
     *              or throws 404 not found
     */
    public function list($userId)
    {
        $rolePermissionsList = $this->getUserPermissionsList($userId);
        $dataCount = count($rolePermissionsList);
        return $this->sendResponse($rolePermissionsList, HttpCode::OK, ['totalCount' => $dataCount]);
    }

    /**
     * get a list with all the permissions and the selected values for the specified user
     *
     * @param number $userId user id for which to get permissions
     * @return array list with permissions [{ id, name, slug, active }]
     *
     */
    private function getUserPermissionsList($userId)
    {
        // make sure the user exists in the database
        User::select(['id'])->findOrFail($userId);

        // get all permissions
        $permissionList = Permission::select(['id', 'name', 'slug'])->get()->toArray();

        // get permissions for the user
        $userPermissions = UsersPermission::where('user_id', $userId)->get();
        // create an indexed array by permission id
        $userPermissionsIndexed = [];
        if (count($userPermissions) > 0) {
            foreach ($userPermissions as $userPermission) {
                $userPermissionsIndexed[$userPermission->permission_id] = 1;
            }
        }

        // create the permissions array and set the permissions active status
        $userPermissionsList = [];
        foreach ($permissionList as $permission) {
            $active = isset($userPermissionsIndexed[$permission['id']]) ? 1 : 0;
            $permissionItem = (object) ['id' => $permission['id'], 'name' => $permission['name'], 'slug' => $permission['slug'], 'active' => $active];
            array_push($userPermissionsList, $permissionItem);
        };

        return $userPermissionsList;
    }

    /**
     * update user permissions in database
     *
     * @param \Illuminate\Http\Request $request http request which must contain only the permission ids which are set
     * @param number $id user id for which to update
     * @return \Illuminate\Http\JsonResponse json object with the saved permissions
     */
    public function update(Request $request, $id)
    {
        $validator = $this->validateUpdateRequest($id, $request);
        if (!$validator['success']) {
            return $this->sendError(['Validation Error'], HttpCode::BadRequest);
        }
        $saveData = $this->getSaveData($id, $validator['data']);
        $this->savePermissions($id, $saveData);
        return $this->sendResponse($saveData);
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
     * export user permissions data
     *
     * @param \Illuminate\Http\Request $request http request, must contain: export_format
     * @param number id user id for which to export permissions
     * @return \Illuminate\Http\Response containing the exported data or a bad request if request not valid
     */
    public function export(Request $request, $id)
    {
        if (!$this->validateExport($request)) {
            return $this->sendError(['Validation Error.'], HttpCode::BadRequest);
        }
        $user = User::select(['first_name', 'last_name'])->findOrFail($id);
        $data = $this->getExportData($request, $id);
        $fileName = 'user-' . $user->first_name . '-' . $user->last_name . '-permissions-' . date('Y-m-d');
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
                $pdf = PDF::loadView($this->exportPath . '.export-table-user-permissions', compact('data'));
                // download pdf file
                return $pdf->download($fileName . '.pdf');
                break;
        }
    }

    /**
     * get data for export from db, for the given request
     *
     * @param \Illuminate\Http\Request $request http request
     * @param number $id role id
     * @return array a list with the permissions for given user id
     */
    private function getExportData(Request $request, $id)
    {
        return $this->getUserPermissionsList($id);
    }

    /**
     * get the csv content for the given data
     *
     * @param array $data array of permission models
     * @return string csv content for provided data
     */
    private function getCsvContent($data)
    {
        $columnList = [__('tables.Id'), __('tables.Name'), __('tables.Slug'), __('tables.Active')];

        return ExportUtils::getCsvContent($data, $columnList, $this->selectFields);
    }

    /** functions used to create / update role - BEGIN */

    /**
     * update user permissions in database, from provided request
     *
     * @param number $userId user id
     * @param array $data an array containing the save data items: [ [userId, permissionId], ... ]
     */
    private function savePermissions($userId, $data)
    {
        // first delete existing permissions for given user
        UsersPermission::where('user_id', $userId)->delete();
        if (empty($data)) {
            return;
        }
        DB::table('users_permissions')->insert($data);
    }

    /**
     * validate update request
     *
     * @param number $userId user id
     * @param \Illuminate\Http\Request $request http request which must contain only the permission ids which are set
     * @return array a response array which contains ['success', 'data']
     */
    private function validateUpdateRequest($userId, Request $request)
    {
        $response = ['success' => false];
        // if request doesn't have ids, return invalid response
        if (!$request->has('ids')) {
            return $response;
        }

        // search for the user in db, if not found the function will throw a 404 not found and the code after this won't be executed
        User::findOrFail($userId);

        // decode the request ids
        $ids = json_decode($request->ids, JSON_NUMERIC_CHECK);
         // if empty request array, return true, to delete all user permissions
        if (empty($ids)) {
            $response['success'] = true;
            $response['data'] = $ids;
            return $response;
        }
        // if fails to make all ids as integers, return invalid response
        $ids = ArrayUtils::transformToPositiveIntegers($ids);
        if (!$ids) {
            return $response;
        }

        // make sure that all sent ids exist in the database, otherwise return invalid response
        $permissionList = Permission::select('id')->whereIn('id', $ids)->get()->toArray();
        if (empty($permissionList) || count($permissionList) !== count($ids)) {
            return $response;
        }

        // return a valid response, containing all the permissions to save
        $response['success'] = true;
        $response['data'] = $ids;
        return $response;
    }

    /**
     * get the data to save in database
     *
     * @param number $userId user id for which to get the save data
     * @param array $permissionIds array with the permission ids which to save in the pivot table user_permssions
     * @return array an empty array if there is no permission to save, otherwise an array containing ['user_id', 'permission_id']
     */
    private function getSaveData($userId, $permissionIds)
    {
        $data = [];
        if (empty($permissionIds)) {
            return $data;
        }

        foreach ($permissionIds as $permissionId) {
            array_push($data, ['user_id' => $userId, 'permission_id' => $permissionId]);
        }
        return $data;
    }

    /** functions used to create / update user - END */

}
