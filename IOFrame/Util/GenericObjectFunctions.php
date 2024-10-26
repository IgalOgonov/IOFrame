<?php
namespace IOFrame\Util{

    define('IOFrameUtilGenericObjectFunctions',true);

    class GenericObjectFunctions{
        /** Generates a simple filter than concats multi-column filter values
         * @param array $params
         *  'filterName': string, name of the filter in 'columnFilters' of the object
         *  'filterColumns': array, key column(s) in our table
         * @return array|false
         */
        public static function filterByMultipleColumns(array $context, array $params): bool|array {
            $requiredParams = ['filterColumns','filterName','baseTableName'];

            foreach ($requiredParams as $param){
                if(empty($params[$param]))
                    return false;
            }
            $columnArrays = $context['params'][$params['filterName']] ?? null;

            if(!$columnArrays)
                return false;

            $prefix = $context['SQLManager']->getSQLPrefix();
            $baseTableName = $prefix.$params['baseTableName'];
            foreach ($params['filterColumns'] as $k => $f){
                $params['filterColumns'][$k] = $baseTableName.'.'.$f;
            }

            $cond = [];

            foreach ($columnArrays as $array){
                $temp = [];
                foreach ($array as $col)
                    $temp[] = [$col, 'STRING'];
                $temp[] = 'CSV';
                $cond[] = $temp;
            }
            $cond[] = 'CSV';
            return [
                $params['filterColumns'],
                $cond,
                'IN'
            ];
        }

        /** Used to generate a filter condition that matches multiple multi-key identifiers in another table, and
         * returns the matching identifiers from the main table
         * @param array $params
         *                      'filterName': string, name of the filter in 'columnFilters' of the object
         *                       'baseTableName': string, main table name
         *                       'foreignTableName': string, foreign table name
         *                       'inputMap': string|array of input keys names, that match the keys in each filter object|string we receive
         *                       'foreignColumns': array, column(s) to match in the other table
         *                       'mainColumns': array, key column(s) in our table
         *                       ['foreignTableKeyMap']: array of the form <string, key column name in main table> => <string, column name in foreign table>
         * @return array[]|false
         */
        public static function filterByStuffInAnotherTable(array $context, array $params): array|bool {
            $requiredParams = ['filterName','baseTableName','foreignTableName','foreignColumns','mainColumns','inputMap'];

            foreach ($requiredParams as $param){
                if(empty($params[$param]))
                    return false;
            }
            $params['foreignTableKeyMap'] = $params['foreignTableKeyMap'] ?? [];

            $filterByIDs = $context['params'][$params['filterName']] ?? null;

            if(!$filterByIDs)
                return false;

            $prefix = $context['SQLManager']->getSQLPrefix();
            $baseTableName = $prefix.$params['baseTableName'];
            $foreignTableName = $prefix.$params['foreignTableName'];

            $filterArr = [];
            foreach ($filterByIDs as $filter){
                if(!is_array($params['inputMap']))
                    $filterArr[] = [$filter[$params['inputMap']], 'STRING'];
                else{
                    $temp = [];
                    foreach ($params['inputMap'] as $filterKey)
                        $temp[] = [$filter[$filterKey], 'STRING'];
                    $temp[] = 'CSV';
                    $filterArr[] = $temp;
                }
            }
            $filterArr[] = 'CSV';

            $foreignColumns = is_array($params['foreignColumns'])? [...$params['foreignColumns'],'CSV'] : $params['foreignColumns'];
            $foreignKeyColumns = [];
            foreach ($params['mainColumns'] as $column)
                $foreignKeyColumns[] = $foreignTableName . '.' . ($params['foreignTableKeyMap'][$column] ?? $column);
            $mainColumns = [];
            foreach ($params['mainColumns'] as $column)
                $mainColumns[] = $baseTableName . '.' . $column;

            $cond = $context['SQLManager']->selectFromTable(
                $foreignTableName,
                [
                    $foreignColumns,
                    $filterArr,
                    'IN'
                ],
                $foreignKeyColumns,
                ['justTheQuery'=>true,'useBrackets'=>true]
            );
            return [
                $mainColumns,
                [$cond,'ASIS'],
                'IN'
            ];
        }
    }

}