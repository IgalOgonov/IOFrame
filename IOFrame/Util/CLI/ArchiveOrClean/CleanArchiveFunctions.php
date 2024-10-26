<?php
namespace IOFrame\Util\CLI\ArchiveOrClean{
    define('IOFrameUtilCLIArchiveOrCleanCleanArchiveFunctions',true);

    class CleanArchiveFunctions{
        /** Used by the default cleaning / archiving CLI.
         * @param array $parameters Runtime parameters from cron-management
         */
        public static function cleanArchiveCommonRun (array &$parameters): array {

            $result = ['exit' => false, 'result'=>[]];
            $finishedTables = [];
            $timedOutTables = [];
            $catastrophicTables = [];

            foreach ($parameters['tables'] as $tableIndex=>$tableInfo){

                \IOFrame\Util\PureUtilFunctions::setDefaults(
                    $parameters['tables'][$tableIndex],
                    [
                        'retries'=>0,
                        'batchIteration'=>0,
                        'batchLag'=>null,
                        'success'=>0
                    ]
                );

                if(!empty($parameters['tables'][$tableIndex]['finished'])){
                    continue;
                }

                if($parameters['tables'][$tableIndex]['retries'] > $parameters['retries']){
                    $timedOutTables[$tableIndex] = true;
                    continue;
                }

                if($parameters['tables'][$tableIndex]['batchLag'] === 'catastrophic'){
                    $catastrophicTables[$tableIndex] = true;
                    continue;
                }

                DynamicCleanArchiveFunctions::dynamicCleanArchive(
                    [
                        'tableInfo'=>$parameters['tables'][$tableIndex],
                        'tableIndex'=>$tableIndex,
                        'startTime'=>$parameters['_startTime']
                    ],
                    $parameters
                );

                if(!empty($parameters['tables'][$tableIndex]['finished'])){
                    $finishedTables[$tableIndex] = true;
                }

                $result['result'][$tableIndex] = [
                    'name'=>$parameters['tables'][$tableIndex]['name'],
                    'retries'=>$parameters['tables'][$tableIndex]['retries'],
                    'successfulItems'=>$parameters['tables'][$tableIndex]['success'],
                    'finished'=>!empty($parameters['tables'][$tableIndex]['finished']),
                ];
            }

            if( ( count($finishedTables) + count($timedOutTables) + count($catastrophicTables) ) === count($parameters['tables']) ){
                $result['exit'] = true;
            }

            return $result;
        }

        /** Used by the default cleaning / archiving CLI, after the main loop finishes.
         * @param array $parameters Runtime parameters from cron-management
         * @param array $errors Errors from cron-management
         * @param string $timeElapsed How much time has passed since start
         * @param string $id How much time has passed since start
         */
        public static function cleanArchiveCommonRunAfter(array $parameters, array &$errors, string $timeElapsed, string $id): void {
            $unfinishedTables = [];
            $catastrophicFailure = [];
            foreach ($parameters['tables'] as $i => $table){
                if(empty($table['finished'])){
                    $unfinishedTables[$i] = $table['name'];
                }
                if($table['batchLag'] === 'catastrophic'){
                    $catastrophicFailure[$i] = $table['name'];
                }
            }
            if(count($unfinishedTables)){
                \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['default-cleanup-unfinished',$id],$unfinishedTables);
            }
            if(count($catastrophicFailure)){
                \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['default-cleanup-catastrophic-failure',$id],$catastrophicFailure);
            }

            if($parameters['verbose']??false)
                foreach ($parameters['tables'] as $table){
                    echo '---- '.$id.' - '.$table['name'].'----'.EOL.
                        ($table['finished']? 'Finished in ' : 'Stopped after ').floor($timeElapsed/1000000).'.'.(floor($timeElapsed/1000)%1000).' sec, '.$table['success'].' items cleaned, '.$table['retries'].' retries'.EOL;
                }
        }
    }

}