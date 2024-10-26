<?php
namespace IOFrame\Util\CLI\ArchiveOrClean{
    define('IOFrameUtilCLIArchiveOrCleanDynamicCleanArchiveFunctions',true);

    class DynamicCleanArchiveFunctions{

        /** Used by the default cleaning / archiving CLI for certain tables.
         * @param array $inputs Examples shown in cron-management dynamicJobProperties defaults, each object with 'tables'
         * @param array $parameters Runtime parameters from cron-management
         */
        public static function dynamicCleanArchive(array $inputs, array &$parameters): void {
            $mainSQLManager = $parameters['defaultParams']['SQLManager'];
            $secondarySQLManager = $parameters['AltDBManager'] ?? $mainSQLManager;
            $archive = ($parameters['archive']??true)&&($parameters['AltDBManager']??false);
            $clean = ($parameters['clean']??true);
            $tableInfo = $inputs['tableInfo'];
            $tableInfoIndex = $inputs['tableIndex'];
            $startTime = ($inputs['startTime']??time()) - ($parameters['considerOld']??0);

            //Select a batch of relevant items
            $results = $mainSQLManager->selectFromTable(
                $mainSQLManager->getSQLPrefix().$tableInfo['name'],
                [
                    $tableInfo['expiresColumn'],
                    $startTime,
                    '<'
                ],
                $tableInfo['identifierColumns'],
                [
                    'test'=>$parameters['test'],
                    'verbose'=>$parameters['verbose'],
                    'limit'=>$parameters['batchSize'],
                    'offset'=>$parameters['batchSize']*$parameters['tables'][$tableInfoIndex]['batchIteration'],
                    'orderBy'=>$tableInfo['expiresColumn'],
                    'orderType'=>0,
                ]
            );

            //If selection failed, signify a retry
            if(gettype($results) !== 'array'){
                $parameters['tables'][$tableInfoIndex]['retries']++;
                return;
            }

            if(count($results) === 0){
                $parameters['tables'][$tableInfoIndex]['finished'] = true;
                return;
            }
            else
                $parameters['tables'][$tableInfoIndex]['batchIteration']++;

            //Archive items
            if($archive && ($parameters['tables'][$tableInfoIndex]['batchLag']??null !== 'clean')){

                $columns = [];
                foreach ($results[0] as $colOrNum => $v){
                    if(preg_match('/^\d+$/',$colOrNum))
                        continue;
                    else
                        $columns[] = $colOrNum;
                }

                $toInsert = [];
                foreach ($results as  $resArr){
                    $row = [];
                    foreach ($columns as $col){
                        $row[] = [$resArr[$col], 'STRING'];
                    }
                    $toInsert[] = $row;
                }
                $success = $secondarySQLManager->insertIntoTable(
                    $secondarySQLManager->getSQLPrefix().$tableInfo['name'],
                    $columns,
                    $toInsert,
                    ['onDuplicateKey'=>true,'test'=>$parameters['test'],'verbose'=>$parameters['verbose']]
                );

                if($success !== true){
                    $parameters['tables'][$tableInfoIndex]['batchIteration']--;
                    $parameters['tables'][$tableInfoIndex]['batchLag'] = 'archive';
                    $parameters['tables'][$tableInfoIndex]['retries']++;
                    return;
                }
                else{
                    if($parameters['tables'][$tableInfoIndex]['batchLag'] === 'archive')
                        $parameters['tables'][$tableInfoIndex]['batchLag'] = null;
                }
            }

            //Try to delete items
            if($clean){

                $stuffToDelete = [];
                foreach ($results as $item){
                    $itemToDelete = [];
                    foreach ($tableInfo['identifierColumns'] as $col)
                        $itemToDelete[] = [$item[$col], 'STRING'];
                    $itemToDelete[] = 'CSV';
                    $stuffToDelete[] = $itemToDelete;
                }

                $deletion = $mainSQLManager->deleteFromTable(
                    $mainSQLManager->getSQLPrefix().$tableInfo['name'],
                    [
                        $tableInfo['identifierColumns'],
                        $stuffToDelete,
                        'IN'
                    ],
                    [ 'test'=>$parameters['test'], 'verbose'=>$parameters['verbose'] ]
                );

                if($deletion !== true){

                    //We only get here if we successfully archived, then failed to delete old files, but didn't retry yet
                    if($archive && ($parameters['tables'][$tableInfoIndex]['batchLag'] !== 'clean')){
                        $deleteArchived = $secondarySQLManager->deleteFromTable(
                            $secondarySQLManager->getSQLPrefix().$tableInfo['name'],
                            [
                                $tableInfo['identifierColumns'],
                                $stuffToDelete,
                                'IN'
                            ],
                            [ 'test'=>$parameters['test'], 'verbose'=>$parameters['verbose'] ]
                        );
                        if($deleteArchived !== true){
                            $parameters['tables'][$tableInfoIndex]['batchLag'] = 'catastrophic';
                            return;
                        }
                    }

                    $parameters['tables'][$tableInfoIndex]['batchIteration']--;
                    $parameters['tables'][$tableInfoIndex]['retries']++;
                    $parameters['tables'][$tableInfoIndex]['batchLag'] = 'clean';
                }
                else{

                    if($parameters['tables'][$tableInfoIndex]['batchLag'] === 'clean')
                        $parameters['tables'][$tableInfoIndex]['batchLag'] = null;
                    else
                        $parameters['tables'][$tableInfoIndex]['batchIteration']--;

                    $parameters['tables'][$tableInfoIndex]['success']+=count($stuffToDelete);

                }
            }

            //If we were testing, finish after 1 iteration
            if($parameters['test'])
                $parameters['tables'][$tableInfoIndex]['finished'] = true;
        }

    }

}
