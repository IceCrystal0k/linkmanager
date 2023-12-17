<?php

namespace App\Http\Controllers\API\Users;

use App\Helpers\ExportUtils;
use App\Http\Controllers\API\BaseController;
use App\Models\Role;
use App\Models\User;
use App\Models\UsersRole;
use Illuminate\Http\Request;
use PDF;

/**
 * class which handles the list / create / update / delete of roles
 */
class UserRolesController extends BaseController
{
    protected $viewPath = 'roles/permissions';
    protected $routePath = 'roles/permissions';
    protected $translationPrefix = 'permission.';
    protected $model = 'App\Models\UsersPermission';
    private $updateFields; // fields that will be updated on save
    private $selectFields;

    public function __construct()
    {
         // $this->middleware(function ($request, $next) {
        //     $this->userSettings = UserUtils::getUserSetting(auth('sanctum')->user()->id);
        //     return $next($request);
        // });
    }

    /**
     * get a list with the permissions of the role
     * @param {number} $roleId role for which to get permissions
     * @return {array} list of permissions : [{ id, name, active }]
     */
    public function list($userId)
    {
        $roleList = Role::select(['id', 'name', 'slug'])->get()->toArray();

        // get role permissions
        $userRoles = UsersRole::where('user_id', $userId)->get();
        $userRolesIndexed = [];
        if (count($userRoles) > 0) {
            foreach ($userRoles as $rolePermission) {
                $userRolesIndexed[$rolePermission->permission_id] = 1;
            }
        }
        $rolePermissionsList = [];
        // set the permissions active status
        foreach ($roleList as $permission) {
            $active = isset($userRolesIndexed[$permission['id']]) ? 1 : 0;
            $permissionItem = (object) ['id' => $permission['id'], 'name' => $permission['name'], 'active' => $active];
            array_push($rolePermissionsList, $permissionItem);
        };

        return $rolePermissionsList;
    }

    /**
     * update user permissions in database
     * @param {object} $request http request
     * @param {number} $id user id for which to update
     * @return {view} edit view
     */
    public function update(Request $request, $id)
    {
        return 'update user roles';
        $this->savePermissions($request, $id);
        return redirect()->route($this->routePath, ['userId' => $id])->with(['success' => __('general.UpdatedSuccess')]);
    }

    /**
     * export selected data
     * @param {object} $request http request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        return 'export user roles';
        $data = $this->getExportData($request);
        $fileName = $this->routePath . '-' . date('Y-m-d');
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
                $pdf = PDF::loadView($this->viewPath . '.export-table', compact('data'));
                // download pdf file
                return $pdf->download($fileName . '.pdf');
                break;
        }
    }

    /**
     * get data for export from db, for the given request
     * @param {object} $request http request
     * @return {array} of role models
     */
    private function getExportData(Request $request)
    {
        $data = $this->model::select($this->selectFields)->get();
        return $data;
    }

    /**
     * get the csv content for the given data
     * @param {array} $data array of role models
     * @return {string} csv content for provided data
     */
    private function getCsvContent($data)
    {
        $columnList = [__('tables.Id'), __('tables.Name'), __('tables.Slug')];

        return ExportUtils::getCsvContent($data, $columnList, $this->selectFields);
    }

    /** functions used to create / update role - BEGIN */

    /**
     * update role in database, from provided request
     * @param {object} $request http request
     * @param {number} $roleId id of the role for which to save the permissions
     */
    private function savePermissions(Request $request, $roleId)
    {
        $permissions = $request->get('permissions');
        UsersPermission::where('role_id', $roleId)->delete();
        if ($permissions !== null) {
            $data = [];
            foreach ($permissions as $permission) {
                array_push($data, ['role_id' => $roleId, 'permission_id' => $permission]);
            }
            \DB::table('roles_permissions')->insert($data);
        }
    }

    /** functions used to create / update role - END */

}
