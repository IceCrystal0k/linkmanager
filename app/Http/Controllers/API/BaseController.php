<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller as Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BaseController extends Controller
{
    protected $exportPath = 'exports';
    /**
     * success response method.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResponse($result, $message, $code = 200)
    {
        $response = $this->createResponse(true, $message, $result, $code);
        return response()->json($response, $code);
    }

    /**
     * return an error response
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = $this->createResponse(false, $error, $errorMessages, $code);
        return response()->json($response, $code);
    }

    /**
     * return an empty response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendEmptyResponse($code = 404)
    {
        return response()->noContent($code);
    }

    /**
     * @return array an associative array
     */
    protected function createResponse($success, $message = '', $data = null, $code = 200)
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'code' => $code,
        ];
        if (!empty($data)) {
            $response['data'] = $data;
        }
        return $response;
    }

    /**
     * validate a list of rules against the rquest
     * @param \Illuminate\Http\Request $request user request object
     * @param array $rules rules to validate
     * @param callable $customValidation optional function to set custom validation
     * @return object response with { failed, errors }
     */
    protected function validateRules(Request $request, array $rules, callable $customValidation = null)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($customValidation) {
            $validator->after(function ($validator) use ($request, $customValidation) {
                $customValidation($request, $validator);
            });
        }
        $failed = $validator->fails();
        $response = (object) ['failed' => $failed, 'errors' => $validator->errors()];
        return $response;
    }
}
