<?php
/* This the the API that handles all the user related functions.
 * Many of the procedures here are timing safe, meaning they will return in constant times (well, constant intervals)
 *
 *      See standard return values at defaultInputResults.php
 *_________________________________________________
 * uploadImages
 *      - Uploads images. By default, only admins have the authentication to do this.
 *        items: (json) Array where  of the form:
 *              [
 *              <upload name 1>: [
 *                              'filename' : string, default '' - if set will save the image file under this name,
 *                                       otherwise with a random one.
 *                              'alt' : string, default '' - alt tag for the image (meta information)
 *                              'name' : string, default '' - "pretty" image name(meta information)
 *                              'caption' : string, default '' - image caption
 *                              ],
 *              <upload name 2>: ...,
 *              ...
 *              ]
 *              items can also be left unset - then, all posted files that are images will be saved with random names.
 *              NOTE: images posted but not specified in "items" will NOT be uploaded.
 *        address : string, path to upload the images, RELATIVE TO IMAGE FOLDER ROOT
 *        imageQualityPercentage : int, default 100, self explanatory
 *        gallery : string, default '' - if set, all uploaded images will be added to this gallery.
 *        overwrite : bool, default false - whether allow overwriting of specific images (relevant if filenames are set)
 *        (also posted, but caught by $_FILES) <upload name 1>
 *        (also posted, but caught by $_FILES) <upload name 2>
 *        ...
 *
 *        Examples: action=uploadImages&address=docs/installScreenshots/testFolder
 *                  action=uploadImages&address=docs/installScreenshots/testFolder&imageQualityPercentage=50&gallery=Test Gallery&
                    items={"image":{"filename":"Test Filename","alt":"Alternative Title","name":"Pretty Name"}}
 *
 *        Returns Array of the form:
 *
 *          [
 *           <uploadName1> => Code/Address,
 *           <uploadName2> => Code/Address,
 *           ...
 *          ]
 *
 *          Codes:
 *          [String] Resource address RELATIVE TO IMAGE ROOT
 *         -1 Image upload would work locally, but failed to connect to DB for needed operations
 *          0 success, but could not return resource address
 *          1 Image of incorrect size/format
 *          2 Could not move image to requested path
 *          3 Could not overwrite existing image
 *          4 Image upload would work locally, but resource update failed
 *          5 Image upload would work locally, but gallery does not exist
 *_________________________________________________
 * getImages
 *      - Gets ALL available images/folders at an address (defaults to root image folder).
 *        address: string, default '' - if not empty, will return images at the folder specified by address, RELATIVE to image root.
 *                                  The default root image folder is <SERVER_ROOT>.'/front/ioframe/img'.
 *
 *        Examples: action=getImages
 *                  action=getImages&addresses=docs
 *                  action=getImages&address=docs/installScreenshots
 *
 *      Returns json Array of the form:
 *      [
 *       <Address> =>   [
 *                                              'address' => Absolute address (if local - compared to server root).
 *                                              'folder' => bool, whether the file is a folder (assumes it contains images)
 *                                              'lastChanged' => int, unix timestamp of the local file
 *                                              [OPTIONAL]'alt' : string, default '' - alt tag for the image (meta information)
 *                                              [OPTIONAL]'name' : string, default '' - "pretty" image name NOT FILENAME
 *                                              [OPTIONAL]'caption' : string, default '' - image caption
 *                        ],
 *      ...
 *      ]
 *          *important note - identifier names are by default relative addresses from the relevant
 *          setting root.
 *          For example, if the file is a JS file address is <$serverRoot>/front/ioframe/js/test folder/test.js,
 *          the identifier is "test folder/test.js" (always 'address'.'name').
 *          What keeps different files in similar addresses unique is the file extension.
 *          Also, local resources that do not actually exist will be ignored.
 *_________________________________________________
 * updateImage
 *      - Updates meta information about the image
 *        address:  string, image address
 *        name:  string, default '' - new name for the image
 *        alt:  string, default '' - alt tag for the image
 *        caption : string, default '' - image caption
 *        Examples: action=updateImage&address=docs/installScreenshots/1.png&name=Amazing Image&alt=A great picture
 *                  action=updateImage&address=docs/installScreenshots/1.png&name=Less Amazing Image
 *                  action=updateImage&address=docs/installScreenshots/1.png&alt=Google, Index this
 *
 *        Returns integer code:
 *             -1 - failed to connect to DB
 *              0 - success
 *              1 - image does not exist
 *_________________________________________________
 * moveImage
 *      - Moves an image from one Address to another (can be used to rename it too)
 *        oldAddress:  string, old Address of the image
 *        newAddress:  string, new Address of the image
 *
 *        Examples: action=moveImage&oldAddress=docs/installScreenshots/1.png&newAddress=docs/installScreenshots/potato.png
 *
 *        Returns integer code:
 *             -1 - failed to connect to DB (this causes local files to be deleted)
 *              0 - success
 *      *note - DOES NOT TELL YOU if a resource does not exist locally - will simply ignore it.
 *_________________________________________________
 * deleteImages
 *      - Deletes images.
 *        addresses:  (json) Array of strings - addresses you want to delete.
 *
 *        Examples: action=deleteImages&addresses=["docs/installScreenshots/testFolder/Test Filename.jpg","test2.png"]
 *
 *        Returns integer code:
 *             -1 - failed to connect to DB (this causes local files to be deleted)
 *              0 - success
 *      *note - DOES NOT TELL YOU if a resource does not exist locally - will simply ignore it.
 *_________________________________________________
 * incrementImages
 *      - Increments image versions images.
 *        addresses:  (json) Array of strings - addresses you want to delete.
 *
 *        Examples: action=incrementImages&addresses=["docs/installScreenshots/testFolder/Test Filename.png","test2.png"]
 *
 *        Returns integer code:
 *             -1 - failed to connect to DB (this causes local files to be deleted)
 *              0 - success
 *      *note - DOES NOT TELL YOU if a resource does not exist locally
 *_________________________________________________
 * getGalleries
 *      - Gets all image galleries availible - but only meta information, not members!.
 *
 *        Examples: action=getGalleries
 *
 *      Returns json Array of the form:
 *      [
 *       <Gallery Name> =>   [
 *                                              'order' => string, comma separated values of gallery order.
 *                                              'created' => int, UNIX timestamp of when gallery was created
 *                                              'lastChanged' =>  int, UNIX timestamp of when gallery was last changed
 *                                              [OPTIONAL]'name' => string, default '' - "pretty" name for a gallery
 *                        ],
 *      ...
 *      ]
 *_________________________________________________
 * getGallery
 *      - Gets image gallery by name.
 *        gallery: String, name of the gallery to get
 *
 *        Examples: action=getGallery&gallery=Test Gallery
 *
 *      Returns
 *      EITHER a json Array of the form:
 *      [
 *      '@' >                       <Gallery info like in getGalleries>
 *       <First Child Address> =>   <Image info like in getImages>,
 *       <Second Child Address> =>   [
 *                                     ...
 *                                  ],
 *      ...
 *      ]
 *      OR code 1, if the gallry does not exist
 *_________________________________________________
 * setGallery
 *      - Creates an image gallery by name.
 *        gallery: String, name of the gallery to create
 *        overwrite: bool, default false - will allow overwriting existing galleries
 *        update: bool, default false - will only update existing galleries
 *        name: String, optional "pretty" name of the gallery (stored as meta information)
 *
 *        Examples: action=setGallery&gallery=Test Gallery&name=Brave New Name&update=true
 *                  action=setGallery&gallery=Another Gallery&name=Name-o-tron
 *
 *        Returns integer code:
 *             -1 - failed to connect to DB (this causes local files to be deleted)
 *              0 - success
 *              1 - Name already exists and 'override' is false, or name doesn't exist and update is true
 *_________________________________________________
 * deleteGallery
 *      - Deletes an image gallery.
 *        gallery: String, name of the gallery to delete
 *
 *        Examples: action=deleteGallery&gallery=Test Gallery
 *
 *        Returns integer code:
 *             -1 - failed to connect to DB (this causes local files to be deleted)
 *              0 - success
 *_________________________________________________
 * addToGallery
 *      - Adds images to gallery.
 *        gallery: String, name of the gallery
 *        addresses:  (json) Array of strings - addresses you want to add.
 *
 *        Examples:
 *          action=addToGallery&gallery=Test Gallery&addresses=["docs/Euler.png","pluginImages/def_icon.png"]
 *          action=addToGallery&gallery=Fake Gallery&addresses=["docs/Euler.png","pluginImages/def_icon.png"]

 *      Returns json Array of the form:
 *          [
 *          <Address> =>   code
 *          ...
 *          ]
 *        where the codes are:
 *              -1 - Could not connect to db.
 *              0 - All good
 *              1 - Resource does not exist
 *              2 - Collection does not exist
 *              3 - Resource already in collection.
 *_________________________________________________
 * removeFromGallery
 *      - Removes images from gallery.
 *        gallery: String, name of the gallery
 *        addresses:  (json) Array of strings - addresses you want to remove.
 *
 *        Examples:
 *          action=removeFromGallery&gallery=Test Gallery&addresses=["docs/Euler.png","pluginImages/def_icon.png"]
 *          action=removeFromGallery&gallery=Fake Gallery&addresses=["docs/Euler.png","pluginImages/def_icon.png"]
 *
 *        Returns integer code:
 *              -1 - Could not connect to db.
 *              0 - All good
 *              1 - Collection does not exist
 *_________________________________________________
 * moveImageInGallery
 *      - Moves an image at a certain index to another index ina gallery.
 *        gallery: String, name of the gallery
 *        from: int, index to move from.
 *        to: int, index to move to.
 *
 *        Examples:
 *          action=moveImageInGallery&gallery=Test Gallery&from=0&to=2
 *          action=moveImageInGallery&gallery=Fake Gallery&from=4&to=2
 *
 *        Returns integer code:
 *                  -1 - Could not connect to DB
 *                  0 - All good
 *                  1 - Indexes do not exist in order
 *                  2 - Collection does not exist
 *_________________________________________________
 * swapImagesInGallery
 *      - Swaps two images in gallery
 *        gallery: String, name of the gallery
 *        num1: int, first index to swap
 *        num2: int, second index to swap
 *
 *        Examples:
 *          action=swapImagesInGallery&gallery=Test Gallery&num1=0&num2=2
 *          action=swapImagesInGallery&gallery=Fake Gallery&num1=4&num2=2
 *
 *        Returns integer code:
 *                  -1 - Could not connect to DB
 *                  0 - All good
 *                  1 - Indexes do not exist in order
 *                  2 - Collection does not exist
 *_________________________________________________
 *
 * */

if(!defined('coreInit'))
    require __DIR__ . '/../main/coreInit.php';

require 'defaultInputChecks.php';
require 'defaultInputResults.php';
require 'CSRF.php';
require 'mediaAPI_fragments/definitions.php';
require __DIR__ . '/../IOFrame/Util/timingManager.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if($test)
    echo 'Testing mode!'.EOL;


//Handle inputs
$inputs = [];

switch($action){

    case 'uploadImages':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["items","address","imageQualityPercentage","gallery","overwrite"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/uploadImages_checks.php';
        require 'mediaAPI_fragments/uploadImages_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'getImages':
        $arrExpected =["address"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/getImages_checks.php';
        require 'mediaAPI_fragments/getImages_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'updateImage':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["address","name","alt"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/updateImage_checks.php';
        require 'mediaAPI_fragments/updateImage_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'moveImage':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["oldAddress","newAddress"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/moveImage_checks.php';
        require 'mediaAPI_fragments/moveImage_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'deleteImages':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["addresses"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/deleteImages_checks.php';
        require 'mediaAPI_fragments/deleteImages_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'incrementImages':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["addresses"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/incrementImages_checks.php';
        require 'mediaAPI_fragments/incrementImages_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'getGalleries':
        $arrExpected =[];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/getGalleries_checks.php';
        require 'mediaAPI_fragments/getGalleries_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'getGallery':
        $arrExpected =["gallery"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/getGallery_checks.php';
        require 'mediaAPI_fragments/getGallery_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setGallery':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["name","gallery","overwrite","update"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/setGallery_checks.php';
        require 'mediaAPI_fragments/setGallery_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'deleteGallery':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["gallery"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/deleteGallery_checks.php';
        require 'mediaAPI_fragments/deleteGallery_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'addToGallery':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["addresses","gallery"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/addToGallery_checks.php';
        require 'mediaAPI_fragments/addToGallery_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'removeFromGallery':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["addresses","gallery"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/removeFromGallery_checks.php';
        require 'mediaAPI_fragments/removeFromGallery_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'moveImageInGallery':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["from","to","gallery"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/moveImageInGallery_checks.php';
        require 'mediaAPI_fragments/moveImageInGallery_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;


    case 'swapImagesInGallery':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["num1","num2","gallery"];

        require 'setExpectedInputs.php';
        require 'mediaAPI_fragments/swapImagesInGallery_checks.php';
        require 'mediaAPI_fragments/swapImagesInGallery_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}

?>