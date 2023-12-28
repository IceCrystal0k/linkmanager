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
     * @param mixed $result the result to return
     * @param number $status the http status code to return
     * @param array $extraInfo additional information to be added to the response, can contain totalCount, etc
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResponse($result, $status = 200, $extraInfo = null)
    {
        $response = $this->createResponse($result, $status, $extraInfo);
        return response()->json($response, $status);
    }

    /**
     * return an error response
     *
     * @param array $errors array with error information
     * @param number $status the http status code to return
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendError($errors, $status = 404)
    {
        $response = $this->createResponse(null, $status, ['errors' => $errors]);
        return response()->json($response, $status);
    }

    /**
     * return an empty response
     *
     * @param number $status the http status code to return
     * @return \Illuminate\Http\Response
     */
    public function sendEmptyResponse($status = 404)
    {
        return response()->noContent($status);
    }

    /**
     * create a response from the provided params
     *
     * @param mixed $data data to return
     * @param number $status the http status code to return
     * @param array $extraInfo extra info to add to the response
     * @return array an associative array with the response
     */
    protected function createResponse($data = null, $status = 200, $extraInfo = null)
    {
        $response = [
            'status' => $status
        ];
        if (!empty($extraInfo)) {
            $response = array_merge($response, $extraInfo);
        }
        if (!empty($data)) {
            $response['data'] = $data;
        }
        return $response;
    }

    /**
     * validate a list of rules against the rquest
     *
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
