<?php
namespace App\Helpers;

class ExportUtils
{
    public static function getCsvContent($data, $columnList, $fieldList)
    {
        $buffer = fopen('php://temp', 'r+');

        fputcsv($buffer, $columnList);
        foreach ($data as $row) {
            $rowData = [];
            foreach ($fieldList as $field) {
                array_push($rowData, $row->{$field});
            }
            fputcsv($buffer, $rowData);
        }

        rewind($buffer);
        $csvContent = '';
        while ($csvLine = fgets($buffer)) {
            $csvContent .= $csvLine;
        }
        fclose($buffer);

        return $csvContent;

    }
}