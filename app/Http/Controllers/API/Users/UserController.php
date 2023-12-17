<?php
namespace App\Http\Controllers\API\Users;

use App\Enums\HttpCode;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Helpers\ArrayUtils;
use App\Helpers\ExportUtils;
use App\Helpers\UserUtils;
use App\Helpers\Form;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\UserEmailToken;
use App\Models\UsersRole;
use App\Models\UsersPermission;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;


class UserController extends BaseController
{
    protected $exportFileName = 'permissions';
    protected $routePath = 'user';
    protected $translationPrefix = 'user.';
    protected $model = 'App\Models\User';
    private $editFields; // fields that appear in the edit form
    private $updateFields; // fields that will be updated on save
    private $selectFields; // fields that will be updated on save
    private $exportFields; // fields that are selected for export
    private $filterFields;
    private $userSettings;

    public function __construct()
    {
        $this->editFields = ['id', 'first_name', 'last_name', 'email', 'google_id', 'fb_id', 'status'];
        $this->updateFields = ['first_name', 'last_name', ['field' => 'status', 'type' => 'bool']];
        $this->selectFields = ['id', 'first_name', 'last_name', 'email', 'google_id', 'fb_id', 'status'];
        $this->filterFields = ['search', 'first_name', 'last_name', 'email'];
        $this->exportFields = ['id', 'full_name', 'email', 'role_name', 'updated_date', 'google', 'facebook', 'status_name'];

        $this->middleware(function ($request, $next) {
            $this->userSettings = UserUtils::getUserSetting(auth()->user()->id);
            return $next($request);
        });
    }

    /**
     * get user list
     *
     * @param \Illuminate\Http\Request $request user request
     * @return array list of users
     */
    public function list(Request $request)
    {
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
                        $filteredQuery = $filteredQuery->where('first_name', 'LIKE', '%' . $filterValue . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $filterValue . '%')
                            ->orWhere('email', 'LIKE', '%' . $filterValue . '%');
                        break;
                    case 'first_name':
                        $filteredQuery = $filteredQuery->where('first_name', 'LIKE', '%' . $filterValue . '%');
                        break;
                    case 'last_name':
                        $filteredQuery = $filteredQuery->where('last_name', 'LIKE', '%' . $filterValue . '%');
                        break;
                    case 'email':
                        $filteredQuery = $filteredQuery->where('email', 'LIKE', '%' . $filterValue . '%');
                        break;
                }
            }
        }
        return $filteredQuery;
    }

    /**
     * store user to database -> create new entry
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
        $response = $this->createItem($request);
        return $this->sendResponse($response, 'User created successfully.', HttpCode::Created);
    }

     /**
     * update user in database
     * @param {object} $request http request
     * @param {number} $id user id to update
     * @return {view} edit view
     */
    public function update(Request $request, $id)
    {
        $validator = $this->validateItemRequest($request, $id);
        if ($validator->failed) {
            return $this->sendError('Validation Error.', $validator->errors, HttpCode::BadRequest);
        }
        $response = $this->saveItem($request, $id);
        return $this->sendResponse($response, 'User updated successfully.');
    }

    /**
     * user get item
     * @param {number} $id category id
     * @return {view} edit view
     */
    public function getItem($id)
    {
        $data = $this->getItemForEdit($id);
        return $this->sendResponse($data, null);
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
     * Delete user from db
     * @param {number} $id user id
     * @return {object} the deleted user object
     */
    public function delete($id)
    {
        $item = $this->model::find($id);
        if ($item) {
            $this->deleteUserEntries($item);
            $item->delete();
            return $this->sendEmptyResponse(HttpCode::NoContent);
        } else {
            return $this->sendEmptyResponse(HttpCode::NotFound);
        }
    }

    /**
     * delete selected users and references from db
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
        $this->deleteUsersEntries($ids);
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
     * export selected data
     * @param {object} $request http request
     * @return \Illuminate\Http\Response
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
                $pdf = Pdf::loadView($this->exportPath . '.export-table-users', compact('data'));
                // download pdf file
                return $pdf->download($fileName . '.pdf');
                break;
        }
    }


    /**
     * handle different requests
     * @param \Illuminate\Http\Request $request
     * @param number $id user id
     * return \Illuminate\Http\JsonResponse
     */
    public function handleRequests(Request $request, $id)
    {
        switch ($request->action) {
            case 'activate': $response = $this->activate($request, $id); break;
            case 'deactivate': $response = $this->deactivate($request, $id); break;
            default: $response = $this->sendError('Bad request', HttpCode::BadRequest);
        }
        return $response;
    }

    private function activate(Request $request, $id)
    {
        return $this->sendError('Not implemented', null, HttpCode::NotImplemented);
    }

    private function deactivate(Request $request, $id)
    {
        return $this->sendError('Not implemented', null, HttpCode::NotImplemented);
    }

    /**
     * validate item request before create / save
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $id permission id
     * @return object an object with the fields { failed, errors }
     */
    private function validateItemRequest(Request $request, $id = null)
    {
        $ruleEmail = ['required', 'email', 'max:255', 'unique:users,email'];
        $rulePassword = ['required', 'min:8', 'max:255'];
        $ruleRepeatPassword = ['required', 'same:password'];

        $statusList = implode(',', [UserStatus::Active, UserStatus::Pending, UserStatus::Deleted]);
        $roleList = implode(',', [UserRole::Admin, UserRole::User]);

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'numeric', 'in:'.$statusList],
            'role' => ['required', 'numeric', 'in:'.$roleList.'']
        ];
        $customValidation = null;
        // add password rules when creating the user or when editing the user and the password is present
        if (!$id || $request->has('password')) {
            $rules['password'] = $rulePassword;
            $rules['repeat_password'] = $ruleRepeatPassword;
            $customValidation = function($request, $validator) {
                return $this->validatePasswordStrength($request, $validator);
            };
        }
        // add email rules only when creating the user
        if (!$id) {
            $rules['email'] = $ruleEmail;
        }

        return $this->validateRules($request, $rules, $customValidation);
    }

    private function validatePasswordStrength(Request $request, $validator)
    {
        if (!$this->verifyPasswordStrength($request->password)) {
            $validator->errors()->add('password', __('account.PasswordStrengthFailed'));
        }
    }

    /**
     * save new user in database, from provided request
     *
     * @param \Illuminate\Http\Request $request user request
     * @return object the created entity
     */
    private function createItem(Request $request)
    {
        $item = new $this->model();
        $createFields = ['first_name', 'last_name', 'email', 'password', ['field' => 'status', 'type' => 'bool']];
        Form::updateModelFromRequest($request, $item, $createFields);

        $item->password = Hash::make($item->password);
        $item->email_verified_at = \Carbon\Carbon::now();
        $item->save();
        // set user role
        $item->roles()->attach($request->role);


        return $item;
    }

    private function verifyPasswordStrength($password)
    {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        return $uppercase && $lowercase && $number && $specialChars;
    }

    /**
     * update permission in database, from provided request
     *
     * @param \Illuminate\Http\Request $request user request
     * @param number $itemId id of the permission to save
     * @return object the updated entity
     */
    private function saveItem(Request $request, $itemId)
    {
        $item = $this->model::findOrFail($itemId);
        $userRoles = $item->roles()->get()->toArray();
        $userRole = null;
        if (!empty($userRoles)) {
            $userRole = $userRoles[0]['id'];
        }
        if ($request->has('password')) {
            array_push($this->updateFields, 'password');
        }
        Form::updateModelFromRequest($request, $item, $this->updateFields);

        $item->password = Hash::make($item->password);
        if (!$item->email_verified_at) {
            $item->email_verified_at = \Carbon\Carbon::now();
        }
        $item->save();
        // set user role
        if ($userRole !== $request->role) {
            $item->roles()->detach($userRole);
            $item->roles()->attach($request->role);
        }
        return $item;
    }

    /**
     * get data for export from db, for the given request
     *
     * @param \Illuminate\Http\Request $request http request
     * @return array a list with all permissions
     */
    private function getExportData(Request $request)
    {
        $exportDateRange = $request->has('export_daterange') ? $request->export_daterange : null;
        $exportStatus = $request->has('export_status') ? $request->export_status : null;
        $exportRole = $request->has('export_role') ? $request->export_role : null;

        $query = $this->model::leftJoin('users_roles', 'users.id', '=', 'users_roles.user_id')
            ->select(['id', DB::raw("CONCAT(first_name,' ', last_name) as full_name"), 'email',
                'updated_at', 'google_id', 'fb_id', 'status', 'role_id']);

        if ($exportDateRange) {
            list($dateStart, $dateEnd) = explode(' - ', $exportDateRange);
            try {
                $dateStart = \Carbon\Carbon::parse($dateStart);
                $dateEnd = \Carbon\Carbon::parse($dateEnd);
                $query->whereBetween('created_at', [$dateStart, $dateEnd]);
            } catch (\Exception$e) {
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
                $query->whereIn('status', $statusListFiltered);
            }
        }

        if ($exportRole) {
            $roleList = is_array($exportRole) ? $exportRole : [];
            $roleListFiltered = [];
            foreach ($roleList as $val) {
                if (is_numeric($val)) {
                    array_push($roleListFiltered, (int) $val);
                }
            }
            if ($roleListFiltered && count($roleListFiltered) > 0) {
                $query->whereIn('role_id', $roleListFiltered);
            }
        }

        $data = $query->get();
        foreach ($data as &$row) {
            // updated_at format can't be changed, so add a new date attribute
            $row->updated_date = date($this->userSettings->date_format_php, strtotime($row->updated_at));
            $row->google = $row->google_id ? __('Yes') : '';
            $row->facebook = $row->fb_id ? __('Yes') : '';
        }

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
        $columnList = [__('tables.Id'), __('tables.Name'), __('tables.Email'), __('tables.Role'), __('tables.UpdatedAt'),
        __('tables.Google'), __('tables.Facebook'), __('tables.Status')];

        return ExportUtils::getCsvContent($data, $columnList, $this->exportFields);
    }

    /**
     * delete all data of a user
     * @param object $user user object
     */
    private function deleteUserEntries($user)
    {
        UserInfo::where('user_id', $user->id)->delete();
        UsersRole::where('user_id', $user->id)->delete();
        UsersPermission::where('user_id', $user->id)->delete();
        UserEmailToken::where('email', $user->email)->delete();
        $user->tokens()->delete();
    }

    private function deleteUsersEntries($usersIds)
    {
        UserInfo::whereIn('user_id', $usersIds)->delete();
        UsersRole::whereIn('user_id', $usersIds)->delete();
        UsersPermission::whereIn('user_id', $usersIds)->delete();
        $usersEmails = User::whereIn('id', $usersIds)->select('email')->pluck('email')->toArray();
        if ($usersEmails) {
            UserEmailToken::whereIn('email', $usersEmails)->delete();
        }
        PersonalAccessToken::whereIn('tokenable_id', $usersIds)
            ->where('tokenable_type', 'APP\Models\User')->delete();
    }


}
