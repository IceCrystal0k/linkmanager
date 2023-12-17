<?php
namespace App\Helpers;

class Form
{

/**
 * verifies if there are differences between the given request and model, for the provided list of fields
 * @param object $request -> UI request
 * @param object $model -> model to be checked
 * @param object $fields -> array with the fields names
 * @return true if there is any difference, false if nothing was changed
 */
    public static function modelHasChanges($request, $model, $fields)
    {
        $hasChanges = false;
        foreach ($fields as $field) {
            if ($request->{$field} != $model->{$field}) {
                $hasChanges = true;
                break;
            }
        }
        return $hasChanges;
    }

/**
 * set the provided model fields with the values from the given request
 * @param object|array $request -> UI request or associative array (request()->old())
 * @param object $model -> model to update
 * @param object $fields -> array with the fields names
 * @return nothing
 */
    public static function updateModelFromRequest($request, $model, $fields)
    {
        $requestType = gettype($request) === 'array' ? 'array' : 'collection';
        foreach ($fields as $field) {
            if (is_array($field)) {
                $fieldName = $field['field'];
                switch ($field['type']) {
                    case 'bool':
                        if ($requestType === 'array') {
                            $model->{$fieldName} = isset($request[$fieldName]) ? 1 : 0;
                        } else {
                            $model->{$fieldName} = $request->has($fieldName) ? 1 : 0;
                        }
                        break;
                    case 'array':
                        if ($requestType === 'array') {
                            $model->{$fieldName} = isset($request[$fieldName]) ? $request[$fieldName] : null;
                        } else {
                            $model->{$fieldName} = $request->has($fieldName) ? $request->get($fieldName) : null;
                        }
                        break;
                    case 'bool_array':
                        if ($requestType === 'array') {
                            if (isset($request[$fieldName])) {
                                $fieldValue = (object) [];
                                foreach ($request[$fieldName] as $key => $value) {
                                    $fieldValue->{$value} = 1;
                                }
                                $model->{$fieldName} = json_encode($fieldValue);
                            } else {
                                $model->{$fieldName} = null;
                            }
                        } else {
                            if ($request->has($fieldName)) {
                                $fieldValue = (object) [];
                                foreach ($request->get($fieldName) as $key => $value) {
                                    $fieldValue->{$value} = 1;
                                }
                                $model->{$fieldName} = json_encode($fieldValue);
                            } else {
                                $model->{$fieldName} = null;
                            }
                        }
                        break;
                }
            } else if ($request) {
                // handle request()->old()
                if ($requestType === 'array') {
                    if (array_key_exists($field, $request)) {
                        $model->{$field} = $request[$field];
                    } else if (!empty($request) && $model->{$field} === 1) {
                        // handle bool values for user changed values
                        $model->{$field} = 0;
                    }
                } // handle request as collection
                else if ($request->has($field)) {
                    $model->{$field} = $request->get($field);
                }
            }
        }
    }

    /**
     * copy the fields from the given source to the destination
     * @param object $source -> object from where to take the data
     * @param object $dest -> object to update
     * @param object $fields -> array with the fields names
     * @return nothing
     */
    public static function copyObjectAttributes($source, $dest, $fields)
    {
        foreach ($fields as $field) {
            $dest->{$field} = $source ? $source->{$field} : null;
        }
    }
}
