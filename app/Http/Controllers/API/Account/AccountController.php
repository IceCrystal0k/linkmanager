<?php

namespace App\Http\Controllers\API\Account;

use App\Enums\HttpCode;
use App\Enums\UserRole;
use App\Helpers\Form;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Mail\Account\AccountDelete;
use App\Mail\Account\ResetPassword;
use App\Mail\Account\VerifyEmail;
use App\Models\User;
use App\Models\UserEmailToken;
use App\Models\UserInfo;
use App\Models\UsersPermission;
use App\Models\UsersRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends BaseController
{

    protected $translationPrefix = 'account.';
    private $updateFields; // fields that will be updated on update profile request
    private $partialUpdateFields; // fields that will be updated on partial update request
    private $customValidation; // callback function for custom validation

    public function __construct()
    {
        $this->updateFields = ['first_name', 'last_name'];
        $this->partialUpdateFields = ['google_id', 'fb_id'];

        $this->customValidation = function ($request, $validator) {
            return $this->validatePasswordStrength($request, $validator);
        };
    }

    /**
     * Register account
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = $this->validateRegister($request);
        if ($validator->failed) {
            return $this->sendError('Validation Error.', $validator->errors, HttpCode::BadRequest);
        }

        // get input data
        $input = $request->all();
        $input['password'] = bcrypt($input['password']); // encrypt the password
        // create the user based on the input fields
        $user = User::create($input);

        // create a custom response
        $response = [];
        $response['token'] = $user->createToken(env('APP_NAME'))->plainTextToken;
        $response['first_name'] = $user->first_name;
        $response['last_name'] = $user->last_name;
        $response['email'] = $user->email;

        // set user role to User
        $user->roles()->attach(UserRole::User);
        // send verification email
        $emailResponse = $this->sendEmailVerificationNotification($user);
        if (!$emailResponse['success']) {
            return $this->sendError($emailResponse['message'], null, $emailResponse['code']);
        }
        return $this->sendResponse($response, 'User registered successfully.', HttpCode::Created);
    }

    /**
     * Delete account
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        $response = ['no_message' => 'just deleted'];
        return $this->sendResponse($response, 'Account deleted.');
    }

    /**
     * Get account data
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function getProfile(Request $request)
    {
        $response = auth('sanctum')->user();
        return $this->sendResponse($response, 'Get profile data');
    }

    /**
     * Update account data
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request)
    {
        $authUser = auth('sanctum')->user();
        $user = User::findOrFail($authUser->id);
        Form::updateModelFromRequest($request, $user, $this->updateFields);
        $user->save();
        $response = $user;
        return $this->sendResponse($response, 'Update profile data');
    }

    /**
     * Partial update account data
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function partialUpdateProfile(Request $request)
    {
        $authUser = auth('sanctum')->user();
        $user = User::findOrFail($authUser->id);
        Form::updateModelFromRequest($request, $user, $this->partialUpdateFields);
        $user->save();
        $response = $user;
        return $this->sendResponse($response, 'Partial update profile data');
    }

    /**
     * Send email, according to the specified action
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function sendEmail(Request $request)
    {
        if (!$request->has('action')) {
            $this->sendError('Invalid action request', null, HttpCode::BadRequest);
        }
        $action = $request->has('action') ? $request->action : null;
        switch ($action) {
            case 'verify-email':$response = $this->sendVerificationEmail();
                break;
            case 'remove-account':$response = $this->sendRemoveAccountEmail();
                break;
            case 'reset-password':$response = $this->sendResetPasswordEmail();
                break;
            default:$response = $this->sendError('Invalid request', null, HttpCode::BadRequest);
                break;
        }
        return $response;
    }

    /**
     * send verification email
     */
    private function sendVerificationEmail()
    {
        $user = auth('sanctum')->user();
        if ($user->email_verified_at) {
            return $this->sendError('Email already verified');
        }

        // send verification email
        $emailResponse = $this->sendEmailVerificationNotification($user);
        if (!$emailResponse['success']) {
            return $this->sendError($emailResponse['message'], null, $emailResponse['code']);
        } else {
            return $this->sendResponse(null, 'Verification email sent');
        }
    }

    /**
     * send email for user to confirm the removing of his account
     */
    private function sendRemoveAccountEmail()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return $this->sendError('User doesn\'t exists');
        }

        // send delete account email
        $emailResponse = $this->sendEmailDeleteNotification($user);
        if (!$emailResponse['success']) {
            return $this->sendError($emailResponse['message'], null, $emailResponse['code']);
        } else {
            return $this->sendResponse(null, 'Delete account email sent');
        }
    }

    /**
     * send email for user to confirm the password rest
     */
    private function sendResetPasswordEmail()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return $this->sendError('User doesn\'t exists');
        }

        // send reset password email
        $emailResponse = $this->sendEmailResetPasswordNotification($user);
        if (!$emailResponse['success']) {
            return $this->sendError($emailResponse['message'], null, $emailResponse['code']);
        } else {
            return $this->sendResponse(null, 'Reset password email sent');
        }
    }

    /**
     * handle different requests
     *
     * @return \Illuminate\Http\Response
     */
    public function handleAccountRequests(Request $request)
    {
        if (!$request->has('action')) {
            $this->sendError('Action not specified', null, HttpCode::BadRequest);
        }

        $action = $request->has('action') ? $request->action : null;
        switch ($action) {
            case 'confirm-email':$response = $this->confirmAccountVerification($request);
                break;
            case 'remove-account':$response = $this->removeAccount($request);
                break;
            case 'token-info':$response = $this->getTokenInfo($request);
                break;
            case 'reset-password':$response = $this->resetPassword($request);
                break;
            default:$response = $this->sendError('Invalid request', null, HttpCode::BadRequest);
                break;
        }
        return $response;
    }

    /**
     * verify account email
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    private function confirmAccountVerification(Request $request)
    {
        $response = ['info' => __($this->translationPrefix . 'AccountRequestErrorInfo')];
        if (!$request->has('token')) {
            return $this->sendError('Account Request Error.', $response, HttpCode::BadRequest);
        }
        $tokenItem = UserEmailToken::where('token', $request->token)->where('action', 'verify-email')->first();
        $validRequest = $this->isValidUserEmailToken($tokenItem);
        if ($validRequest) {
            $tokenItem->delete();
            // activate the user
            $user = User::where('email', $tokenItem->email)->first();
            if ($user) {
                $user->email_verified_at = \Carbon\Carbon::now();
                $user->save();
                return $this->sendResponse(null, 'Account confirmed');
            }
            $response['info'] = __($this->translationPrefix . 'AccountRequestUserNotFound');
        }
        return $this->sendError('Account Request Error.', $response, HttpCode::BadRequest);
    }

    /**
     * completely remove user and references from db
     *
     * @param \Illuminate\Http\Request
     * @param number $id user id
     * @return \Illuminate\Http\Response
     */
    private function removeAccount(Request $request)
    {
        // check request token
        $response = ['info' => __($this->translationPrefix . 'AccountRequestErrorInfo')];
        if (!$request->has('token')) {
            return $this->sendError('Account Request Error.', $response, HttpCode::BadRequest);
        }

        // verify if it's a valid request
        $tokenItem = UserEmailToken::where('token', $request->token)->where('action', 'remove-account')->first();
        $validRequest = $this->isValidUserEmailToken($tokenItem);
        if ($validRequest) {
            // remove the token
            $tokenItem->delete();
            // delete the user
            $user = User::where('email', $tokenItem->email)->first();
            if ($user) {
                $this->deleteUserEntries($user);
                $user->delete();
                return $this->sendResponse(null, 'Account deleted');
            }
            $response['info'] = __($this->translationPrefix . 'AccountRequestUserNotFound');
        }
        return $this->sendError('Account Request Error.', $response, HttpCode::BadRequest);
    }

    /**
     * delete all user entries from database
     */
    private function deleteUserEntries($user)
    {
        UserInfo::where('user_id', $user->id)->delete();
        UsersRole::where('user_id', $user->id)->delete();
        UsersPermission::where('user_id', $user->id)->delete();
        UserEmailToken::where('email', $user->email)->delete();
        $user->tokens()->delete();
    }

    /**
     * Get token info for reset password
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    private function getTokenInfo(Request $request)
    {
        $response = ['info' => __($this->translationPrefix . 'AccountRequestErrorInfo')];
        if (!$request->has('token')) {
            return $this->sendError('Account Request Error.', $response, HttpCode::BadRequest);
        }
        $tokenItem = UserEmailToken::where('token', $request->token)->where('action', 'reset-password')->first();
        $validRequest = $this->isValidUserEmailToken($tokenItem);
        if ($validRequest) {
            $response['info'] = $tokenItem->email;
            return $this->sendResponse($response, 'Token info');
        }
        return $this->sendError('Account Request Error.', $response, HttpCode::BadRequest);
    }

    /**
     * Reset password
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function resetPassword(Request $request)
    {
        $response = ['info' => __($this->translationPrefix . 'AccountRequestErrorInfo')];
        if (!$request->has('token')) {
            return $this->sendError('Account Request Error.', $response, HttpCode::BadRequest);
        }

        // check if the request is valid
        $tokenItem = UserEmailToken::where('token', $request->token)->where('action', 'reset-password')->first();
        $validRequest = $this->isValidUserEmailToken($tokenItem);
        if ($validRequest) {
            $validator = $this->validatePassword($request);
            if ($validator->failed) {
                return $this->sendError('Validation Error.', $validator->errors, HttpCode::BadRequest);
            }

            $tokenItem->delete();
            // update the user password
            $user = User::where('email', $tokenItem->email)->first();
            if ($user) {
                $this->updatePassword($user, $request->password);
                return $this->sendResponse(null, 'Password reset');
            }
            $response['info'] = __($this->translationPrefix . 'AccountRequestUserNotFound');
        }
        return $this->sendError('Account Request Error.', $response, HttpCode::BadRequest);
    }

    /**
     * validate registration request
     *
     * @param \Illuminate\Http\Request
     * @return object response with { failed, errors }
     */
    private function validateRegister(Request $request)
    {
        $rules = [
            'first_name' => ['required', 'max:255'],
            'last_name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'min:8', 'max:255'],
            'repeat_password' => 'required|same:password',
        ];
        return $this->validateRules($request, $rules, $this->customValidation);
    }

    /**
     * validate password strength
     *
     * @param \Illuminate\Http\Request
     * @return object response with { failed, errors }
     */
    protected function validatePasswordStrength(Request $request, $validator)
    {
        if (!$this->verifyPasswordStrength($request->password)) {
            $validator->errors()->add('password', __('account.PasswordStrengthFailed'));
        }
    }

    /**
     * validate password
     *
     * @param \Illuminate\Http\Request
     * @return object response with { failed, errors }
     */
    private function validatePassword(Request $request)
    {
        $rules = [
            'password' => ['required', 'min:8', 'max:255'],
            'repeat_password' => 'required|same:password',
        ];

        return $this->validateRules($request, $rules, $this->customValidation);
    }


    /**
     * verify password strength
     *
     * @param string $password
     * @return true if password strength is verified
     */
    private function verifyPasswordStrength($password)
    {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        return $uppercase && $lowercase && $number && $specialChars;
    }

    /**
     * update password
     *
     * @param \app\Models\User $user user model
     * @param string $password
     */
    private function updatePassword($user, $password)
    {
        $user->password = bcrypt($password);
        $user->save();
    }

    /**
     * send verification email
     *
     * @param \app\Models\User $user user model
     * @return array response containing [success, message, code ]
     */
    private function sendEmailVerificationNotification($user)
    {
        $accessToken = hash('sha256', Str::random(40));

        $response = $this->createVerificationEntry($user->email, $accessToken);
        if (!$response['success']) {
            return $response;
        }

        $message = (object) [
            'name' => $user->first_name . ' ' . $user->last_name,
            'website' => url('/'),
            'verifyLink' => url('/') . '/account-verification/' . $accessToken,
            'subject' => 'Account confirmation',
        ];
        try {
            Mail::to($user->email)->send(new VerifyEmail($message));
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = HttpCode::UnprocessableEntity;
        }
        return $response;
    }

    /**
     * send email confirmation for delete account
     *
     * @param \app\Models\User $user user model
     * @return array response containing [success, message, code ]
     */
    private function sendEmailDeleteNotification($user)
    {
        $accessToken = hash('sha256', Str::random(40));

        $response = $this->createDeleteEntry($user->email, $accessToken);
        if (!$response['success']) {
            return $response;
        }

        $message = (object) [
            'name' => $user->first_name . ' ' . $user->last_name,
            'website' => url('/'),
            'deleteLink' => url('/') . '/account-delete/' . $accessToken,
            'subject' => 'Account delete confirmation',
        ];
        try {
            Mail::to($user->email)->send(new AccountDelete($message));
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = HttpCode::UnprocessableEntity;
        }
        return $response;
    }

    /**
     * send email confirmation for reset password
     *
     * @param \app\Models\User $user user model
     * @return array response containing [success, message, code ]
     */
    private function sendEmailResetPasswordNotification($user)
    {
        $accessToken = hash('sha256', Str::random(40));

        $response = $this->createResetPasswordEntry($user->email, $accessToken);
        if (!$response['success']) {
            return $response;
        }

        $message = (object) [
            'name' => $user->first_name . ' ' . $user->last_name,
            'website' => url('/'),
            'resetLink' => url('/') . '/reset-password/' . $accessToken,
            'subject' => 'Reset password',
        ];
        try {
            Mail::to($user->email)->send(new ResetPassword($message));
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = HttpCode::UnprocessableEntity;
        }
        return $response;
    }

    /**
     * create new token for email verification
     *
     * @param string $email user email
     * @param string $accessToken access token
     *
     * @return array an associative array containing [success, message, code]
     */
    private function createVerificationEntry($email, $accessToken)
    {
        return $this->createUserEmailToken($email, 'verify-email', $accessToken);
    }

    /**
     * create new token for account delete
     *
     * @param string $email user email
     * @param string $accessToken access token
     *
     * @return array an associative array containing [success, message, code]
     */
    private function createDeleteEntry($email, $accessToken)
    {
        return $this->createUserEmailToken($email, 'remove-account', $accessToken);
    }

    /**
     * create new token for reset password
     *
     * @param string $email user email
     * @param string $accessToken access token
     *
     * @return array an associative array containing [success, message, code]
     */
    private function createResetPasswordEntry($email, $accessToken)
    {
        return $this->createUserEmailToken($email, 'reset-password', $accessToken);
    }

    /**
     * Create a new email token entry in the database for the given email and action
     *
     * @param {string} $email   email for which to create the token
     * @param {string} $action  the action for which to create the token
     * @param {string} $accessToken the access token
     * @return array an associative array containing [success, message, code]
     */
    private function createUserEmailToken($email, $action, $accessToken)
    {
        $response = $this->createResponse(true);
        $userEmailToken = UserEmailToken::where('email', $email)->where('action', $action)->first();
        // verify if there is already an entry in the database for the given email and action
        if ($userEmailToken) {
            // allow only one email / 5 minutes
            if ($userEmailToken->created_at) {
                $requestDate = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $userEmailToken->created_at);
                $now = \Carbon\Carbon::now();
                $diffMinutes = $requestDate->diffInMinutes($now);
                if ($diffMinutes < 5) {
                    $response['success'] = false;
                    $response['message'] = $this->getTranslatedAction($action);
                    $response['code'] = HttpCode::TooManyRequests;
                    return $response;
                }
            }
        } else {
            $userEmailToken = new UserEmailToken();
        }

        $userEmailToken->email = $email;
        $userEmailToken->action = $action;
        $userEmailToken->token = $accessToken;
        $userEmailToken->created_at = \Carbon\Carbon::now();
        $userEmailToken->save();

        return $response;
    }

    /**
     * verify the email token
     *
     * @param \app\Models\UserEmailToken $tokenItem
     * @return true if is a valid token, false otherwise
     */
    private function isValidUserEmailToken($tokenItem)
    {
        $validRequest = false;
        if ($tokenItem && $tokenItem->created_at) {
            $requestDate = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $tokenItem->created_at);
            $now = \Carbon\Carbon::now();
            $diffHours = $requestDate->diffInHours($now);
            // verify if the token was created in the last 24 hours
            if ($diffHours <= 24) {
                $validRequest = true;
            }
        }
        return $validRequest;
    }

    /**
     * translate the given action
     *
     * @param string @action
     * @return string the translated action
     */
    private function getTranslatedAction($action)
    {
        $key = '';
        switch ($action) {
            case 'remove-account':$key = 'AccountDeletedMailFrequency';
                break;
            case 'verify-email':$key = 'AccountVerifyMailFrequency';
                break;
        }
        return __($this->translationPrefix . $key);
    }
}
