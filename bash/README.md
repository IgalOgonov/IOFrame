This script is used to initiate a VM.  
Obviously, it is used to download this repo as well.
If you already extracted the repo, run this script with NO_GIT_CLONE="1"

The examples below  (running from the same dir, same script name, after chmod u+x) are helpful if you want to manually test the commands on a new setup, or create a docker based on them.  
Examples:  
./init.sh IOFRAME_TEST_RUN=1   
./init.sh IOFRAME_TEST_RUN=1 IOFRAME_SITE_TLD="example.com" IOFRAME_CERTBOT_EMAIL="bob@securemail.com"   
./init.sh IOFRAME_TEST_RUN=1 IOFRAME_SITE_TLD="example.com" IOFRAME_CERTBOT_EMAIL="bob@securemail.com" IOFRAME_NO_APACHE_INSTALL=1 IOFRAME_NO_APACHE_CONFIG=1 IOFRAME_NO_PHP_INSTALL=1 IOFRAME_NO_PHP_CONFIG=1  
./init.sh IOFRAME_TEST_RUN=1 IOFRAME_OLD_PHP_VER="php7.2" IOFRAME_NO_ADMINER=1 IOFRAME_NO_CERTS=1 IOFRAME_NO_GIT_CLONE=1 IOFRAME_REMOTE_SQL=1 IOFRAME_REMOTE_REDIS=1 IOFRAME_REDIS_BIND_CONFIG="135.1.0.3 135.1.0.4" IOFRAME_REDIS_PASSWORD="LONG_SECURE_PASSWORD"  
./init.sh IOFRAME_TEST_RUN=1 IOFRAME_SITE_TLD="example.com" IOFRAME_CERTBOT_EMAIL="bob@securemail.com" IOFRAME_REDIS_BIND_CONFIG="127.0.0.1 134.1.2.3" IOFRAME_PHP_REDIS_PORT="6380" IOFRAME_CREATE_SQL_DB="ioframe_test" IOFRAME_SQL_USER_USERNAME="superadmin" IOFRAME_SQL_USER_PASSWORD="LONG_SECURE_PASSWORD"  
./init.sh IOFRAME_TEST_RUN=1 IOFRAME_VHOST="cheap.domain.com" IOFRAME_CREATE_SQL_DB="cheat_site"