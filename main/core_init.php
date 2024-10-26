<?php /** @noinspection ALL */

define('IOFrameMainCoreInit',true);

require __DIR__ . '/../vendor/autoload.php';

require 'definitions.php';

//--------------------Initialize dynamic includes-------------------------
require 'core_init_fragments/initiate_dynamic_includes.php';
//--------------------Initialize global settings parameters---------------
require 'core_init_fragments/initiate_default_settings_params.php';
//--------------------Initialize local settings handlers------------------
require 'core_init_fragments/initiate_local_settings.php';
//--------------------Initialize the root folder (for short syntax later)-
require 'core_init_fragments/initiate_path_to_root.php';
//--------------------Initialize redis settings/handler-------------------
require 'core_init_fragments/initiate_redis.php';
//--------------------Decide what mode of operation we're in--------------
require 'core_init_fragments/set_op_mode.php';
//--------------------Initialize sql handler------------------------------
require 'core_init_fragments/initiate_sql.php';
//--------------------Initialize site settings handler--------------------
require 'core_init_fragments/initiate_combined_settings.php';
//--------------------Initialize logging----------------------------------
require 'core_init_fragments/initiate_logging.php';
//--------------------Web Exclusive---------------------------------------
if (\php_sapi_name() !== "cli") {
    //----------------Handle Maintenance Mode-------------
    require 'core_init_fragments/handle_maintenance_local.php';
    require 'core_init_fragments/handle_maintenance_global.php';
    //----------------Handle Redirections-----------------
    require 'core_init_fragments/handle_redirection.php';
    //----------------Set secure cookie settings----------
    require 'core_init_fragments/secure_cookies.php';
    //---------------Initialize session-------------------
    require 'core_init_fragments/initiate_session.php';
    //---------------Initialize AuthHandler---------------
    require 'core_init_fragments/initiate_auth.php';
    //---------------Handle Sticky Cookie-----------------
    require 'core_init_fragments/handle_sticky_cookie.php';
    //---------------Handle Auto-logging------------------
    require 'core_init_fragments/handle_auto_relog.php';
    //---------------Regenerate CSRF----------------------
    require 'core_init_fragments/regenerate_csrf.php';
    //---------------Handle Plugins-----------------------
    require 'core_init_fragments/handle_plugins.php';
}



