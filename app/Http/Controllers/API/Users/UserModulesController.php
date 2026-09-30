<?php

namespace App\Http\Controllers\API\Users;

use App\Enums\HttpCode;
use App\Helpers\ArrayUtils;
use App\Helpers\ExportUtils;
use App\Http\Controllers\API\BaseController;
use App\Models\Role;
use App\Models\Module;
use App\Models\User;
use App\Models\UsersRole;
use App\Models\UsersModule;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * class which handles the list / update of user roles
 */
class UserModulesController extends BaseController
{
    protected $translationPrefix = 'module.';
    protected $model = 'App\Models\UsersModule';
    /** @var string[] */
    private array $selectFields;
    private int $userId;

    public function __construct()
    {
        $this->selectFields = ['id', 'name', 'slug', 'order_index', 'enabled', 'description'];
        $this->middleware(function ($request, $next) {
            // $this->userSettings = UserUtils::getUserSetting(auth('sanctum')->user()->id);
            $this->userId = auth('sanctum')->user()->id;
            return $next($request);
        });
    }

    /**
     * get a list with the roles of the user
     *
     * @return \Illuminate\Http\JsonResponse response which contains the list with roles  [{ id, name, slug, active }]
     *              or throws a 404 not found
     */
    public function list()
    {
        $userModulesList = $this->getUserModulesList($this->userId);
        $dataCount = count($userModulesList);
        return $this->sendResponse($userModulesList, HttpCode::OK, ['totalCount' => $dataCount]);
    }

    /**
     * get a list with all the modules and the selected values for the specified user
     *
     * @param Integer $userId user id for which to get roles
     * @return array list with modules [{ id, name, slug, active, 'order_index', 'enabled', 'description' }]
     *
     */
    private function getUserModulesList($userId)
    {
        // make sure the user exists in the database
        User::select(['id'])->findOrFail($userId);

        // get all modules
        $moduleList = Module::select(['id', 'name', 'slug', 'description', 'icon'])->where('enabled', 1)->get()->toArray();

        // get module for user
        $userModules = UsersModule::where('user_id', $userId)->orderBy('order_index')->get();
        // create an indexed array by module id
        $modulesIndexed = [];
        if (count($moduleList) > 0) {
            foreach ($moduleList as $module) {
                $modulesIndexed[$module['id']] = $module;
            }
        }

        // create the modules array and set the attributes
        $userModulesList = [];
        foreach ($userModules as $userModule) {
            $module = isset($modulesIndexed[$userModule->id]) ? $modulesIndexed[$userModule->id] : null;
            if ($module) {
                $userModuleItem = (object) [
                    'id' => $module['id'],
                    'name' => $module['name'],
                    'slug' => $module['slug'],
                    'order_index' => $userModule['order_index'],
                    'description' => $module['description'],
                    'icon' => $module['icon']
                ];
                array_push($userModulesList, $userModuleItem);
            }
        };

        return $userModulesList;
    }

    /**
     * update user roles in database
     *
     * @param \Illuminate\Http\Request $request http request which must contain only the roles ids which are set
     * @param Integer $id user id for which to update
     * @return \Illuminate\Http\JsonResponse json object with the saved roles
     */
    public function update(Request $request)
    {
        $validator = $this->validateUpdateRequest($this->userId, $request);
        if (!$validator['success']) {
            return $this->sendError(['Validation Error'], HttpCode::BadRequest);
        }
        $saveData = $this->getSaveData($this->userId, $validator['data']);
        $this->saveRoles($this->userId, $saveData);
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
     * export user roles data
     *
     * @param \Illuminate\Http\Request $request http request, must contain: export_format
     * @param Integer id user id for which to export roles
     * @return \Illuminate\Http\Response containing the exported data or a bad request if request not valid
     */
    public function export(Request $request, $id)
    {
        if (!$this->validateExport($request)) {
            return $this->sendError(['Validation Error.'], HttpCode::BadRequest);
        }
        $user = User::select(['first_name', 'last_name'])->findOrFail($id);
        $data = $this->getExportData($request, $id);
        $fileName = 'user-' . $user->first_name . '-' . $user->last_name . '-roles-' . date('Y-m-d');
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
                $pdf = PDF::loadView($this->exportPath . '.export-table-user-roles', compact('data'));
                // download pdf file
                return $pdf->download($fileName . '.pdf');
                break;
        }
    }

    /**
     * get data for export from db, for the given request
     *
     * @param \Illuminate\Http\Request $request http request
     * @param Integer $id user id
     * @return array a list with the roles for given user id
     */
    private function getExportData(Request $request, $id)
    {
        return $this->getUserRolesList($id);
    }

    /**
     * get the csv content for the given data
     *
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
     * update user roles in database, from provided data
     *
     * @param Integer $userId role id
     * @param array $data an array containing the save data items: [ [userId, roleId], ... ]
     */
    private function saveRoles($userId, $data)
    {
        // first delete existing roles for given user
        UsersRole::where('user_id', $userId)->delete();
        if (empty($data)) {
            return;
        }
        DB::table('users_roles')->insert($data);
    }

    /**
     * validate update request
     *
     * @param Integer $userId user id
     * @param \Illuminate\Http\Request $request http request which must contain only the roles ids which are set
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
        // if empty request array, return true, to delete all user roles
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
        $roleList = Role::select('id')->whereIn('id', $ids)->get()->toArray();
        if (empty($roleList) || count($roleList) !== count($ids)) {
            return $response;
        }

        // return a valid response, containing all the role to save
        $response['success'] = true;
        $response['data'] = $ids;
        return $response;
    }

    /**
     * get the data to save in database
     *
     * @param Integer $userId user id for which to get the save data
     * @param array $roleIds array with the role ids which to save in the pivot table user_permssions
     * @return array an empty array if there is no role to save, otherwise an array containing ['user_id', 'role_id']
     */
    private function getSaveData($userId, $roleIds)
    {
        $data = [];
        if (empty($roleIds)) {
            return $data;
        }

        foreach ($roleIds as $roleId) {
            array_push($data, ['user_id' => $userId, 'role_id' => $roleId]);
        }
        return $data;
    }

    /** functions used to create / update role - END */
}
