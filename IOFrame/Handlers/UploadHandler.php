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

        /**Handles uploading an image, and writing it to a location (local or remote).
         * @credit Credit to Geordy James at https://shareurcodes.com/ for some of this code
         *
         * @param string[] $uploadNames Array of the names of the uploaded files (under "name" in the form).
         *                  Can also contain a 2 slot array where Arr[0] is the uploaded file name, and Arr[1] is
         *                  a specific name you want to give to the file (otherwise it's randomly generated).
         *                  Defaults to [], then will push each file in $_FILES that is of type "image/*" and give it
         *                  a random name.
         *
         * @param array $params is an associated array of the form:
         * [
         *          [
         *           'maxImageSize' => int, default 1000000 - maximum upload size in bytes.
         *           'overwrite' => bool, default false - whether overwriting existing images is allowed
         *           'imageQualityPercentage' => int, default 100 - can be 0 to 100
         *           'resourceOpMode' => string, default 'local' - where to upload to (only local implemented at the time)
         *           'resourceTargetPath' => string, default 'front/ioframe/img/' - where to upload to
         *           'createFolders' => bool, default true - create folders in resourceTargetPath when they dont exist
         *          ]
         * ]
         * @returns array Codes or resource address for each upload name, of the form:
         *
         *          [
         *           <uploadName1> => Code/Address,
         *           <uploadName2> => Code/Address,
         *           ...
         *          ]
         *
         *          Codes:
         *          [String] Resource address
         *         -1 unimplemented opMode / incorrect input format
         *          0 success, but could not return resource address
         *          1 Image of incorrect size/format
         *          2 Could not move image to requested path
         *          3 Could not overwrite existing image
         *
        */
        function handleUploadedImage(array $uploadNames, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            //Set maximum image size.
            if(isset($params['maxImageSize']))
                $maxImageSize = $params['maxImageSize'];
            else{
                if($this->siteSettings != null && $this->siteSettings->getSetting('maxUploadSize'))
                    $maxImageSize = $this->siteSettings->getSetting('maxUploadSize');
                else
                    $maxImageSize = 1000000;
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
            else{
                die('Only local mode has been implemented for this handler!');
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
                    if(preg_match('/image\//',$info['type']))
                        array_push($uploadNames,$uploadName);
                }
            }

            foreach($uploadNames as $uploadName){
                $requestedName = '';

                //Support an array type that writes image under a specific name.
                //NOTE that it's on the validation layer to ensure the requested names do not overlap anything.
                if(gettype($uploadName) === 'array'){
                    $requestedName = isset($uploadName['requestedName'])? $uploadName['requestedName'] : '';
                    $uploadName = isset($uploadName['uploadName'])? $uploadName['uploadName'] : '';
                }

                if($uploadName === ''){
                    $res[$uploadName] = -1;
                    if($verbose)
                        echo 'Error - upload name of image not set!'.EOL;
                    continue;
                }

                $res[$uploadName] = 0;

                // Image information
                $uploaded_name = $_FILES[ $uploadName ][ 'name' ];
                $uploaded_ext  = substr( $uploaded_name, strrpos( $uploaded_name, '.' ) + 1);
                $uploaded_size = $_FILES[ $uploadName ][ 'size' ];
                $uploaded_type = $_FILES[ $uploadName ][ 'type' ];
                $uploaded_tmp  = $_FILES[ $uploadName ][ 'tmp_name' ];

                // Where are we going to be writing to?
                $target_path   = $resourceTargetPath;
                $target_file   =  ($requestedName !== '') ?
                    $requestedName.'.'.$uploaded_ext : md5( uniqid().$uploaded_name ).'.'.$uploaded_ext;
                $temp_file     = ( ( ini_get( 'upload_tmp_dir' ) == '' ) ? ( sys_get_temp_dir() ) : ( ini_get( 'upload_tmp_dir' ) ) );
                $temp_file    .= '/' . md5( uniqid() . $uploaded_name ) . '.' . $uploaded_ext;

                // Is it an image?
                if( ( in_array(strtolower( $uploaded_ext ),['jpg','jpeg','png']) ) &&
                    ( $uploaded_size < $maxImageSize ) &&
                    ( $uploaded_type == 'image/jpeg' || $uploaded_type == 'image/png' ) &&
                    getimagesize( $uploaded_tmp ) ) {
                    // Strip any metadata, by re-encoding image (Note, using php-Imagick is recommended over php-GD)
                    if( $uploaded_type == 'image/jpeg' ) {
                        if(!$test){
                            $img = imagecreatefromjpeg( $uploaded_tmp );
                            imagejpeg( $img, $temp_file, $imageQualityPercentage);
                        }
                        if($verbose)
                            echo 'Writing JPEG image to temp directory'.EOL;
                    }
                    else {
                        if(!$test){
                            $img = imagecreatefrompng( $uploaded_tmp );
                            imagepng( $img, $temp_file, 9*(1-$imageQualityPercentage/100));
                        }
                        if($verbose)
                            echo 'Writing PNG image to temp directory'.EOL;
                    }
                    if(!$test)
                        imagedestroy( $img );

                    switch($opMode){
                        case 'local':
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
                        default:
                            if($verbose)
                                echo 'Unimplemented operation mode of UploadHandler!'.EOL;
                            $res[$uploadName] = -1;
                    }
                    // Delete any temp files
                    if( file_exists( $temp_file ) ){
                        if(!$test)
                            unlink( $temp_file );
                        if($verbose)
                            echo 'Deleteing file at '.$temp_file.EOL;
                    }
                }
                // Invalid file
                else {
                    if($verbose)
                        echo 'Image '.$uploadName.' was not uploaded. We can only accept JPEG or PNG images of size up to '.$maxImageSize.EOL;
                    $res[$uploadName] = 1;
                }
            }

            return $res;

        }



    }
}