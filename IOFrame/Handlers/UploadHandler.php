<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('UploadHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    if(!defined('FileHandler'))
        require 'FileHandler.php';

    /*  This class handles local and remote file uploading.
     *
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class UploadHandler extends IOFrame\abstractDBWithCache
    {

        protected $siteSettings = null;

        /** Standard constructor
         *
         * @param SettingsHandler $settings The standard settings object
         * @param array $params - All parameters share the name/type of the class variables
         * */
        function __construct(SettingsHandler $settings, array $params = []){

            parent::__construct($settings,$params);

            $this->siteSettings = $params['siteSettings'];

        }

        /**Handles uploading a file, and writing it to a location (local or remote).
         * @credit Credit to Geordy James at https://shareurcodes.com/ for some of this code
         *
         * @param string $uploadNames Array of the names of the uploaded files (under "name" in the form).
         *                  Can also contain a 2 slot array where Arr[0] is the uploaded file name, and Arr[1] is
         *                  a specific name you want to give to the file (otherwise it's randomly generated).
         *                  Defaults to [], then will push each file in $_FILES and give it
         *                  a random name.
         *
         * @param array $params is an associated array of the form:
         * [
         *          [
         *           'safeMode' => bool, default false - If true, will only support 'jpg','jpeg','png' files.
         *           'maxFileSize' => int, default 1000000 - maximum upload size in bytes.
         *           'overwrite' => bool, default false - whether overwriting existing files is allowed
         *           'imageQualityPercentage' => int, default 100 - can be 0 to 100
         *           'resourceOpMode' => string, default 'local' - where to upload to. 'local' for local server, 'data' to simply return data information, TODO'db' for directly to db
         *           'resourceTargetPath' => string, default 'front/ioframe/img/' - where to upload to
         *           'createFolders' => bool, default true - create folders in resourceTargetPath when they dont exist
         *          ]
         * ]
         * @returns array Codes or resource address for each upload name, of the form:
         *
         *          [
         *           <uploadName1> => Code/Address/File Data,
         *           <uploadName2> => Code/Address/File Data,
         *           ...
         *          ]
         *
         *          Codes:
         *         -2 file upload error
         *         -1 unimplemented opMode / incorrect input format
         *          0 success, but could not return resource address
         *          1 File of incorrect size/format
         *          2 Could not move file to requested path
         *          3 Could not overwrite existing file
         *          4 safeMode param is true, and current format cannot be safely uploaded
         *
         *         [String]Address:
         *          Relative address of the resource
         *
         *         [Array]File Data:
         *          {
         *              'name'=><string, initial name>,
         *              'type'=><string, provided type like application/pdf or image/jpeg>,
         *              'size'=><int, ORIGINAL size>,
         *              'data'=><string, BASE64 ENCODED binary data of the file>
         *          }
         *
        */
        function handleUploadedFile(array $uploadNames, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $safeMode = isset($params['safeMode'])? $params['safeMode'] : false;

            //Set maximum file size.
            if(isset($params['maxFileSize']))
                $maxFileSize = $params['maxFileSize'];
            else{
                if($this->siteSettings != null && $this->siteSettings->getSetting('maxUploadSize'))
                    $maxFileSize = $this->siteSettings->getSetting('maxUploadSize');
                else
                    $maxFileSize = 1000000;
            }

            //Operation Mode
            if(isset($params['overwrite']))
                $overwrite = $params['overwrite'];
            else
                $overwrite = false;

            //Image Quality
            if(isset($params['imageQualityPercentage']))
                $imageQualityPercentage = $params['imageQualityPercentage'];
            else
                $imageQualityPercentage = 100;

            //Operation Mode
            if(isset($params['resourceOpMode']))
                $opMode = $params['resourceOpMode'];
            else
                $opMode = 'local';

            //Target Path
            if($opMode == 'local'){
                $resourceTargetPath = isset($params['resourceTargetPath'])?
                    $params['resourceTargetPath'] : 'front/ioframe/img/';
            }

            //Create folders if they dont exist?
            if(isset($params['createFolders']))
                $createFolders = $params['createFolders'];
            else
                $createFolders = true;

            //Resault
            $res = [];

            if($uploadNames === []){
                foreach($_FILES as $uploadName => $info){
                    if(!$info['error'])
                        array_push($uploadNames,$uploadName);
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
                    $requestedName = isset($uploadName['requestedName'])? $uploadName['requestedName'] : '';
                    $uploadName = isset($uploadName['uploadName'])? $uploadName['uploadName'] : '';
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
                        echo 'Image '.$uploadName.' was not uploaded due to an upload error.'.EOL;
                    $res[$uploadName] = -2;
                }
                // Is it small enough?
                elseif( ( $uploaded_size >= $maxFileSize )
                ){
                    if($verbose)
                        echo 'Image '.$uploadName.' was not uploaded. We can only accept files of size up to '.$maxFileSize.EOL;
                    $res[$uploadName] = 1;
                }
                // Invalid file
                else {
                    // Strip any metadata, by re-encoding image
                    if( $uploaded_type == 'image/jpeg' ) {
                        if($verbose)
                            echo 'Writing JPEG image to temp directory'.EOL;
                        $img = imagecreatefromjpeg( $uploaded_tmp );
                        imagejpeg( $img, $temp_file, $imageQualityPercentage);
                    }
                    elseif( $uploaded_type == 'image/png') {
                        if($verbose)
                            echo 'Writing PNG image to temp directory'.EOL;
                        $img = imagecreatefrompng( $uploaded_tmp );
                        imagesavealpha($img, TRUE);
                        imagepng( $img, $temp_file, 9*(1-$imageQualityPercentage/100));
                    }
                    elseif( $uploaded_type == 'image/webp') {
                        if($verbose)
                            echo 'Writing WebP image to temp directory'.EOL;
                        $img = imagecreatefromwebp( $uploaded_tmp);;
                        imagewebp($img, $temp_file, $imageQualityPercentage);
                    }
                    //Anything bellow this cannot be safely uploaded (at least for now)
                    elseif($safeMode){
                        if($verbose)
                            echo 'File '.$uploadName.' was not uploaded. Only files of types jpeg and png are accepted when safe mode is enabled.'.EOL;
                        $res[$uploadName] = 4;
                        continue;
                    }
                    else{
                        if($verbose)
                            echo 'Uploaded file is of type '.$uploaded_type.', moving to '.$temp_file.EOL;
                        //For consistency
                        rename($uploaded_tmp,$temp_file);
                    }
                    if(isset($img))
                        imagedestroy( $img );

                    switch($opMode){
                        case 'local':
                            $target_path   = $resourceTargetPath;
                            // Can we move the file to the web root from the temp folder?
                            if(!$test){
                                $basePath = $this->settings->getSetting('absPathToRoot') . $resourceTargetPath;

                                //If the folder doesn't exist, and the setting is true, create it
                                if($createFolders && !is_dir($basePath))
                                    mkdir($basePath,0777,true);

                                $writePath = $basePath . $target_file;
                                if(!$overwrite && file_exists($writePath)){
                                    $res[$uploadName] = 3;
                                    $moveFile = false;
                                    if($verbose)
                                        echo 'Error - image already exists at '.$target_path.$target_file.'!'.EOL;
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
                                    echo "<a href='${target_path}${target_file}'>${target_file}</a> succesfully uploaded!".EOL;
                            }
                            else {
                                // No
                                if($verbose)
                                    echo 'Image '.$uploadName.' was not uploaded.'.EOL;
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
}