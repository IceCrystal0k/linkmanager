<?php

namespace App\Http\Controllers\API\Roles;

use App\Enums\HttpCode;
use App\Helpers\ArrayUtils;
use App\Helpers\ExportUtils;
use App\Http\Controllers\API\BaseController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolesPermission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * class which handles the list / create / update / delete of roles
 */
class RolePermissionsController extends BaseController
{
    protected $translationPrefix = 'permission.';
    protected $model = 'App\Models\RolesPermission';
    private $updateFields; // fields that will be updated on save
    private $selectFields;
    private $filterFields;

    public function __construct()
    {
        $this->selectFields = ['id', 'name', 'slug', 'active'];
        // $this->middleware(function ($request, $next) {
        //     $this->userSettings = UserUtils::getUserSetting(auth('sanctum')->user()->id);
        //     return $next($request);
        // });
    }

    /**
     * get a list with all the permissions and the selected values for the specified role
     * @param number $roleId role for which to get permissions
     * @return array list of permissions : [{ id, name, active }]
     */
    public function list($roleId)
    {
        $permissionList = Permission::select(['id', 'name', 'slug'])->get()->toArray();

        // get role permissions
        $rolePermissions = RolesPermission::where('role_id', $roleId)->get();
        $rolePermissionsIndexed = [];
        if (count($rolePermissions) > 0) {
            foreach ($rolePermissions as $rolePermission) {
                $rolePermissionsIndexed[$rolePermission->permission_id] = 1;
            }
        }
        $rolePermissionsList = [];
        // set the permissions active status
        foreach ($permissionList as $permission) {
            $active = isset($rolePermissionsIndexed[$permission['id']]) ? 1 : 0;
            $permissionItem = (object) ['id' => $permission['id'], 'name' => $permission['name'], 'slug' => $permission['slug'], 'active' => $active];
            array_push($rolePermissionsList, $permissionItem);
        };

        return $rolePermissionsList;
    }

    /**
     * update role permissions in database
     * @param \Illuminate\Http\Request $request http request which must contain only the permission ids which are set
     * @param number $id role id for which to update
     * @return \Illuminate\Http\JsonResponse json object with the saved permissions
     */
    public function update(Request $request, $id)
    {
        $validator = $this->validateUpdateRequest($id, $request);
        if (!$validator['success']) {
            return $this->sendError('Validation Error.', null, HttpCode::BadRequest);
        }
        $saveData = $this->getSaveData($id, $validator['data']);
        $this->savePermissions($id, $saveData);
        return $this->sendResponse($saveData, 'Role permissions updated successfully.');
    }

    private function validateExport(Request $request)
    {
        if (!$request->has('export_format')) {
            return false;
        }
        return true;
    }

    /**
     * export selected data
     * @param \Illuminate\Http\Request $request http request, must contain: export_format
     * @param number id role id for which to export permissions
     * @return \Illuminate\Http\Response containing the exported data
     */
    public function export(Request $request, $id)
    {
        if (!$this->validateExport($request)) {
            return $this->sendError('Validation Error.', null, HttpCode::BadRequest);
        }
        $role = Role::select('slug')->findOrFail($id);
        $data = $this->getExportData($request, $id);
        $fileName = 'role-' . $role->slug . '-permissions-' . date('Y-m-d');
        $exportFormat = $request->has('export_format') ? $request->export_format : null;

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
                $pdf = PDF::loadView($this->exportPath . '.export-table-role-permissions', compact('data'));
                // download pdf file
                return $pdf->download($fileName . '.pdf');
                break;
        }
    }

    /**
     * get data for export from db, for the given request
     * @param \Illuminate\Http\Request $request http request
     * @param number $id role id
     * @return array with permissions for given role id
     */
    private function getExportData(Request $request, $id)
    {
        $data = $this->list($id);
        return $data;
    }

    /**
     * get the csv content for the given data
     * @param array $data array of role models
     * @return string csv content for provided data
     */
    private function getCsvContent($data)
    {
        $columnList = [__('tables.Id'), __('tables.Name'), __('tables.Slug'), __('tables.Active')];

        return ExportUtils::getCsvContent($data, $columnList, $this->selectFields);
    }

    /** functions used to create / update role - BEGIN */

    /**
     * update role permissions in database, from provided data
     * @param number $roleId role id
     * @param array $data an array containing the save data items: [ [roleId, permissionId], ... ]
     * @return array updated permissions
     */
    private function savePermissions($roleId, $data)
    {
        // first delete existing permissions for given role
        RolesPermission::where('role_id', $roleId)->delete();
        if (empty($data)) {
            return;
        }
        DB::table('roles_permissions')->insert($data);
    }

    /**
     * validate update request
     * @param number $roleId role id
     * @param object $request http request which must contain only the permission ids which are set
     * @return array a response array which contains ['success', 'data']
     */
    private function validateUpdateRequest($roleId, Request $request)
    {
        $response = $this->createResponse(false);
        // if request doesn't have ids, return invalid response
        if (!$request->has('ids')) {
            return $response;
        }

        // search for the role in db, if not found the function will throw a 404 not found and the code after this won't be executed
        Role::findOrFail($roleId);

        // decode the request ids
        $ids = json_decode($request->ids, JSON_NUMERIC_CHECK);
         // if empty request array, return true, to delete all role permissions
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
     * @param number $roleId role id for which to save the permissions
     * @param array $permissionIds array with the permission ids which to save in the pivot table role_permssions
     * @return {array} an empty array if there is no permission to save, otherwise an array containing ['role_id', 'permission_id']
     */
    private function getSaveData($roleId, $permissionIds)
    {
        $data = [];
        if (empty($permissionIds)) {
            return $data;
        }

        foreach ($permissionIds as $permissionId) {
            array_push($data, ['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        return $data;
    }

    /** functions used to create / update role - END */

}
