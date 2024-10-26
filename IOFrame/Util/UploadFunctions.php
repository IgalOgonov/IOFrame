<?php
namespace IOFrame\Util;

define('IOFrameUtilUploadFunctions',true);

class UploadFunctions{

    /**Handles uploading a file, and writing it to a location (local or remote).
     * @credit Credit to Geordy James at https://shareurcodes.com/ for some of this code
     *
     * @param array $uploadNames Array of the names of the uploaded files (under "name" in the form).
     *                  Can also contain a 2 slot array where Arr[0] is the uploaded file name, and Arr[1] is
     *                  a specific name you want to give to the file (otherwise it's randomly generated).
     *                  Defaults to [], then will push each file in $_FILES and give it
     *                  a random name.
     *
     * @param array $params is an associated array of the form:
     * [
     *          [
     *           'absPathToRoot' => string, required - absolute path to project root.
     *           'safeMode' => bool, default false - If true, will only support 'jpg','jpeg','png' files.
     *           'acceptedFormats' => string[], default [] - specific formats to accept during safeMode, even if unsupported.
     *           'maxFileSize' => int, default 1000000 - maximum upload size in bytes.
     *           'overwrite' => bool, default false - whether overwriting existing files is allowed
     *           'imageQualityPercentage' => int, default 100 - can be 0 to 100
     *           'resourceOpMode' => string, default 'local' - where to upload to. 'local' for local server, 'data' to simply return data information, TODO'db' for directly to db
     *           'resourceTargetPath' => string, default 'front/ioframe/img/' - where to upload to
     *           'createFolders' => bool, default true - create folders in resourceTargetPath when they dont exist
     *          ]
     * ]
     * @return array|int
     */
    public static function handleUploadedFile(array $uploadNames, array $params = []): int|array {

        $test = $params['test'] ?? false;
        $verbose = $params['verbose'] ?? $test;
        $safeMode = $params['safeMode'] ?? false;

        if(empty($params['absPathToRoot']))
            return -1;
        else
            $absPathToRoot = $params['absPathToRoot'];


        $acceptedFormats = $params['acceptedFormats'] ?? [];
        $maxFileSize = $params['maxFileSize'] ?? 1000000;
        $overwrite = $params['overwrite'] ?? false;
        $imageQualityPercentage = $params['imageQualityPercentage'] ?? 100;
        $opMode = $params['resourceOpMode'] ?? 'local';
        $resourceTargetPath = $params['resourceTargetPath'] ?? 'front/ioframe/img/';
        $createFolders = $params['createFolders'] ?? true;

        //Resault
        $res = [];

        if($uploadNames === []){
            foreach($_FILES as $uploadName => $info){
                if(!$info['error'])
                    $uploadNames[] = $uploadName;
                else{
                    if($verbose)
                        echo 'Error - upload '.$uploadName.' failed!'.EOL;
                    $res[$uploadName] = -2;
                }
            }
        }

        foreach($uploadNames as $uploadName){
            $requestedName = '';

            //Support an array type that writes a file under a specific name.
            //NOTE that it's on the validation layer to ensure the requested names do not overlap anything.
            if(gettype($uploadName) === 'array'){
                $requestedName = $uploadName['requestedName'] ?? '';
                $uploadName = $uploadName['uploadName'] ?? '';
            }

            if($uploadName === ''){
                $res[$uploadName] = -1;
                if($verbose)
                    echo 'Error - upload name of file not set!'.EOL;
                continue;
            }

            $res[$uploadName] = 0;

            //Fix a small possible error
            if(!isset($_FILES[ $uploadName ]) && isset($_FILES[ str_replace(' ','_',$uploadName) ]))
                $uploadedResource = $_FILES[ str_replace(' ','_',$uploadName) ];
            else
                $uploadedResource = $_FILES[ $uploadName ];

            // File information
            $uploaded_name = $uploadedResource[ 'name' ];
            $uploaded_ext  = substr( $uploaded_name, strrpos( $uploaded_name, '.' ) + 1);
            $uploaded_size = $uploadedResource[ 'size' ];
            $uploaded_type = $uploadedResource[ 'type' ];
            $uploaded_tmp  = $uploadedResource[ 'tmp_name' ];

            // Where are we going to be writing to?
            $target_file   =  ($requestedName !== '') ?
                $requestedName.'.'.$uploaded_ext : md5( uniqid().$uploaded_name ).'.'.$uploaded_ext;
            $temp_file     = ( ( ini_get( 'upload_tmp_dir' ) == '' ) ? ( sys_get_temp_dir() ) : ( ini_get( 'upload_tmp_dir' ) ) );
            $temp_file    .= '/' . md5( uniqid() . $uploaded_name ) . '.' . $uploaded_ext;
            //Was there an error?
            if(!empty($uploadedResource[ 'error' ])){
                if($verbose)
                    echo 'File '.$uploadName.' was not uploaded due to an upload error.'.EOL;
                $res[$uploadName] = -2;
            }
            // Is it small enough?
            elseif( ( $uploaded_size >= $maxFileSize )
            ){
                if($verbose)
                    echo 'File '.$uploadName.' was not uploaded. We can only accept files of size up to '.$maxFileSize.EOL;
                $res[$uploadName] = 1;
            }
            // Invalid file
            else {
                //Sometimes, images are named incorrectly but can still be read as their proper type
                $detectedType = exif_imagetype($uploaded_tmp);

                //Image specific
                $supportedTypes = [
                    IMAGETYPE_JPEG => 'image/jpeg',
                    IMAGETYPE_PNG => 'image/png',
                    IMAGETYPE_WBMP => 'image/webp',
                ];
                if($detectedType && !empty($supportedTypes[$detectedType]))
                    $uploaded_type = $supportedTypes[$detectedType];
                $img = null;
                switch ($uploaded_type){
                    case 'image/jpeg':
                        $img = imagecreatefromjpeg( $uploaded_tmp );
                        imagejpeg( $img, $temp_file, $imageQualityPercentage);
                        break;
                    case 'image/png':
                        $img = imagecreatefrompng( $uploaded_tmp );
                        imagesavealpha($img, TRUE);
                        imagepng( $img, $temp_file, 9*(1-$imageQualityPercentage/100));
                        break;
                    case 'image/webp':
                        $img = imagecreatefromwebp( $uploaded_tmp);
                        imagewebp($img, $temp_file, $imageQualityPercentage);
                        break;
                }

                //Anything below this cannot be safely uploaded (at least for now), but exceptions can be made - e.g for text files, or audio files
                if($img){
                    if($verbose)
                        echo 'Wrote '.$uploaded_type.' image to temp directory'.EOL;
                }
                elseif($safeMode && !in_array($uploaded_type,$acceptedFormats)){
                    if($verbose)
                        echo 'File '.$uploadName.' was not uploaded. Only some image file types are accepted by default, when safe mode is enabled.'.EOL;
                    $res[$uploadName] = 4;
                    continue;
                }
                else{
                    if($verbose)
                        echo 'Uploaded file is of type '.$uploaded_type.', moving to '.$temp_file.EOL;
                    //For consistency
                    rename($uploaded_tmp,$temp_file);
                }
                //Cleanup
                if($img)
                    imagedestroy( $img );

                switch($opMode){
                    case 'local':
                        $target_path   = $resourceTargetPath;
                        // Can we move the file to the web root from the temp folder?
                        if(!$test){
                            $basePath = $absPathToRoot . $resourceTargetPath;

                            //If the folder doesn't exist, and the setting is true, create it
                            if($createFolders && !is_dir($basePath))
                                mkdir($basePath,0777,true);

                            $writePath = $basePath . $target_file;
                            if(!$overwrite && file_exists($writePath)){
                                $res[$uploadName] = 3;
                                $moveFile = false;
                                if($verbose)
                                    echo 'Error - file already exists at '.$target_path.$target_file.'!'.EOL;
                            }
                            else
                                $moveFile= rename(
                                    $temp_file,
                                    $writePath
                                );
                        }
                        else
                            $moveFile = true;

                        if($moveFile) {
                            $res[$uploadName] = $target_path.$target_file;
                            // Yes!
                            if($verbose)
                                echo "<a href='$target_path$target_file'>$target_file</a> succesfully uploaded!".EOL;
                        }
                        else {
                            // No
                            if($verbose)
                                echo 'File '.$uploadName.' was not uploaded.'.EOL;
                            $res[$uploadName] = 2;
                        }
                        break;
                    case 'data':
                        $res[$uploadName] = [
                            'data'=>base64_encode(file_get_contents($temp_file)),
                            'name'=>$uploadedResource['name'],
                            'type'=>$uploadedResource['type'],
                            'size'=>$uploadedResource['size']
                        ];
                        break;
                    case 'db':
                        break;
                    default:
                        if($verbose)
                            echo 'Unimplemented operation mode of UploadHandler!'.EOL;
                        $res[$uploadName] = -1;
                }
                // Delete any temp files
                if( file_exists( $temp_file ) ){
                    unlink( $temp_file );
                    if($verbose)
                        echo 'Deleting file at '.$temp_file.EOL;
                }
            }
        }

        return $res;

    }
}
