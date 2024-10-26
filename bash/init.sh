#! /usr/bin/bash
# Remember to run "sudo chmod u+x init.sh"
#
# Required parameters (unless IOFRAME_VHOST is set):
# IOFRAME_SITE_TLD | IOFRAME_SITE_TLDS_MULTIPLE:  Your domain for certbot eg. "text.example.com" | "text.example.com, test2.example.com" respectively
# IOFRAME_CERTBOT_EMAIL: 				          Your email certbot e.g. name@example.com
#
# Optional parameters:
# IOFRAME_TEST_RUN:     Will echo the commands which would be executed. Will have zero effect, and wont necessarily test correctness
#                       Used this way because presumably "set -n" may not work properly on some bash versions, so I'd rather not risk it.
# IOFRAME_OLD_PHP_VER: 	    Default PHP version Apache2 might have enabled e.g. php7.2 - even though a2enmod should already resolve this regardless.
# IOFRAME_TARGET_PHP_VER: 	 Default 8.0, PHP version we want to download.
# IOFRAME_NO_APACHE_INSTALL:If set, will not install Apache
# IOFRAME_NO_APACHE_CONFIG: If set, will not config Apache
# IOFRAME_NO_PHP_INSTALL:   If set, will not install PHP
# IOFRAME_NO_PHP_CONFIG:    If set, will not config Apache
# IOFRAME_NO_ADMINER:		If set, will not install adminer
# IOFRAME_NO_CERTS:		  If set, will not automatically install certificates with certbot. For now, IOFRAME_VHOST forces this to be false;
# IOFRAME_NO_GIT_CLONE:	If set, will assume git repo is already cloned /var/www/html[/$IOFRAME_VHOST]
# IOFRAME_REMOTE_SQL:		If set, will not install mariadb on this machine
# IOFRAME_REMOTE_REDIS: If set, will not install redis on this machine, and config PHP session to use remote address
# IOFRAME_REDIS_BIND_CONFIG:    If set, will bind Redis to different ports than the default e.g. "0.0.0.0", "127.0.0.1 134.1.2.3"
#					              If IOFRAME_REMOTE_REDIS if false, PHP config will still be set to 127.0.0.1:$IOFRAME_PHP_REDIS_PORT.
#					              Otherwise, will use this value for the PHP config.
#					              Multiple / duplicate addresses are supported, but in this script version, they will all be appended the same port / password.
#					              Moreover, they will not have separate weights/timeout/etc.
#					              You'd have to go into /etc/php/8.0/apache2/php.ini and edit those manually, at least for now.
# IOFRAME_REDIS_PASSWORD: 	If set, will add redis password, and automatically authenticate PHP with it.
# 					        If you add IOFRAME_REDIS_PASSWORD, make it a hex string worth at least 128 (better - 256) bits of randomness.
# IOFRAME_PHP_REDIS_PORT	Default 6379 - port to use for PHP redis session
# IOFRAME_CREATE_SQL_DB:      If set, will create a DB of this name
# IOFRAME_SQL_USER_USERNAME:  If set, will create an SQL user with all privileges
# IOFRAME_SQL_USER_PASSWORD:  Required password for new SQL user. Same rules as IOFRAME_REDIS_PASSWORD should apply.
# IOFRAME_VHOST: 	If you want to copy your IOFrame into a VHOST  e.g. "text.example.com"
#					        VHOST apache config is done via different bash script (at least for now), so this will prevent certbot from running.

#Import named arguments
for ARGUMENT in "$@"
do
     KEY=$(echo "$ARGUMENT" | cut -f1 -d=)

    KEY_LENGTH=${#KEY}
    VALUE="${ARGUMENT:$KEY_LENGTH+1}"

    export "$KEY"="$VALUE"
done

if [ -v IOFRAME_TEST_RUN ]; then
  echo 'Running in test mode';
fi

#Ensure required arguments exist, unless IOFRAME_VHOST is set
if ! [[ -v IOFRAME_VHOST ]]; then
  if ! [ -v IOFRAME_CERTBOT_EMAIL ]; then
    echo "Enter variable IOFRAME_CERTBOT_EMAIL :"
    read -r temp
    # shellcheck disable=SC2140
    export "IOFRAME_CERTBOT_EMAIL"="$temp"
  fi
  if ! [ -v IOFRAME_SITE_TLD ] && ! [ -v IOFRAME_SITE_TLDS_MULTIPLE ] ; then
    echo "Enter variable IOFRAME_SITE_TLD :"
    read -r temp
    # shellcheck disable=SC2140
    export "IOFRAME_SITE_TLD"="$temp"
  fi
fi
if ! [ -v IOFRAME_PHP_REDIS_PORT ]; then
  # shellcheck disable=SC2140
  export "IOFRAME_PHP_REDIS_PORT"="6379"
fi
if ! [ -v IOFRAME_PHP_REDIS_PORT ]; then
  # shellcheck disable=SC2140
  export "IOFRAME_TARGET_PHP_VER"="8.0"
fi

if ! [ -v IOFRAME_TEST_RUN ]; then
  # Update, get relevant repos, update again
  sudo apt update
  sudo apt upgrade -y
  sudo apt install -y software-properties-common dirmngr apt-transport-https wget ca-certificates

  if ! [ -v IOFRAME_NO_PHP_INSTALL ]; then
    sudo add-apt-repository -y ppa:ondrej/php
  fi

  if ! [ -v IOFRAME_REMOTE_REDIS ]; then
      sudo add-apt-repository -y ppa:redislabs/redis
  fi

  if ! [ -v IOFRAME_REMOTE_SQL ]; then
    sudo apt-key adv --fetch-keys 'https://mariadb.org/mariadb_release_signing_key.asc'
    sudo add-apt-repository -y 'deb [arch=amd64,arm64,ppc64el] https://atl.mirrors.knownhost.com/mariadb/repo/10.8/ubuntu jammy main'
  fi

  sudo apt update

  # Install (L)AMP, Redis, Adminer, Snap, Certbot, Composer

  if ! [ -v IOFRAME_NO_PHP_INSTALL ]; then
    sudo apt install -y php"$IOFRAME_TARGET_PHP_VER" php"$IOFRAME_TARGET_PHP_VER"-common php"$IOFRAME_TARGET_PHP_VER"-dev php"$IOFRAME_TARGET_PHP_VER"-dom php"$IOFRAME_TARGET_PHP_VER"-iconv php"$IOFRAME_TARGET_PHP_VER"-bcmath php"$IOFRAME_TARGET_PHP_VER"-mysql php"$IOFRAME_TARGET_PHP_VER"-redis php"$IOFRAME_TARGET_PHP_VER"-curl php"$IOFRAME_TARGET_PHP_VER"-mbstring php"$IOFRAME_TARGET_PHP_VER"-gd php"$IOFRAME_TARGET_PHP_VER"-zip php-pear
    # Enforce PHP Version
    sudo update-alternatives --set php /usr/bin/php"$IOFRAME_TARGET_PHP_VER"

    # Install PHPRedis
    sudo pecl channel-update pecl.php.net
    # TODO Press enter 6 times
    sudo pecl install redis
  fi

  if ! [ -v IOFRAME_NO_APACHE_INSTALL ]; then
    sudo apt install -y apache2
    # Might be forced to manually disable a different version of PHP before enabling this one, if so, uncomment following line with relevant PHP version (e.g. php7.2)
    if [[ -v IOFRAME_OLD_PHP_VER ]]; then
      sudo a2dismod "$IOFRAME_OLD_PHP_VER"
    fi
    sudo a2enmod php"$IOFRAME_TARGET_PHP_VER"
  fi

  if ! [ -v IOFRAME_REMOTE_SQL ]; then
    sudo apt install -y mariadb-server
  fi

  if ! [ -v IOFRAME_REMOTE_REDIS ]; then
    sudo apt install -y redis
  fi

  # Up to you to specify adminer destination when DB is remote
  if ! [ -v IOFRAME_NO_ADMINER ]; then
    sudo apt install -y adminer
  fi

  # Yes, SSDNodes, among other VM providers, has lots of outdated garbage. Hope this is the last direct download here
  if ! [ -v IOFRAME_NO_CERTS ]; then
    sudo curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
    sudo apt install -y snapd
    sudo snap install --classic certbot
  fi
  # Install IOFrame, change ownership of folder to PHP's user and install with composer
  if ! [[ -v IOFRAME_NO_GIT_CLONE ]]; then

    if [[ -v IOFRAME_VHOST ]]; then
      sudo git clone https://github.com/IgalOgonov/IOFrame.git /var/www/html/"$IOFRAME_VHOST"
    else
      sudo rm -rf /var/www/html
      sudo git clone https://github.com/IgalOgonov/IOFrame.git /var/www/html
    fi

    sudo chown -R www-data /var/www/html

    #PHP Composer
    if [[ -v IOFRAME_VHOST ]]; then
      cd /var/www/html/"$IOFRAME_VHOST" || exit
    else
      cd /var/www/html || exit
    fi
    sudo composer install -q

  fi

  #Redis stuff
  if ! [ -v IOFRAME_REMOTE_REDIS ]; then
    sudo systemctl enable --now redis-server
    if [[ -v IOFRAME_REDIS_BIND_CONFIG ]]; then
      sed -i -e "s/^bind .*/bind $IOFRAME_REDIS_BIND_CONFIG/g" /etc/redis/redis.conf
    fi
    if [[ -v IOFRAME_REDIS_PASSWORD ]]; then
      sed -i -e "s/^\#* requirepass .*/ requirepass ${IOFRAME_REDIS_PASSWORD}/g" /etc/redis/redis.conf
    fi
  fi

  #PHP Config - Change to relevant version
  # Redis
  # Yes, most of those will also replace all default examples in comments. I dont care
  # You can also use the syntax auth[username]=USERNAME[password]=NEW_PASSWORD, if you create the user first, but not via this script
  # Remember that multiple bindings will all use the same port and password - even if they are duplicate.
  if ! [ -v IOFRAME_NO_PHP_CONFIG ]; then
    sed -i -e 's/\;*extension=redis//g' \
      -e 's/\;extension=openssl/\;extension=openssl\nextension=redis /g' \
      -e 's/\;*session\.save_handler =.*/session.save_handler = redis/g' \
      -e 's/session\.gc_maxlifetime =.*/session.gc_maxlifetime = 31536000/g' \
      -e 's/\;*date.timezone =.*/date.timezone = "UTC"/g' \
      -e 's/upload_max_filesize =.*/upload_max_filesize = 1024M/g' \
      -e 's/post_max_size =.*/post_max_size = 1024M/g' \
      -e 's/\;*session\.cookie_samesite =.*/session.cookie_samesite = "Strict"/g' \
      -e 's/\;*session\.cookie_httponly =.*/session.cookie_httponly = 1/g' \
      -e 's/\;*session\.use_only_cookies =.*/session.use_only_cookies = 1/g' \
      -e 's/\;*session\.cookie_secure =.*/session.cookie_secure = 1/g' \
      /etc/php/"$IOFRAME_TARGET_PHP_VER"/apache2/php.ini

    if [ -v IOFRAME_REMOTE_REDIS ] && [ -v IOFRAME_REDIS_BIND_CONFIG ] ; then
      temp="";
      for BIND in $IOFRAME_REDIS_BIND_CONFIG
      do
          temp2="tsp:\/\/$BIND:$IOFRAME_PHP_REDIS_PORT "
          if [ -v IOFRAME_REDIS_PASSWORD ] ; then
            temp2="$temp2?auth=$IOFRAME_REDIS_PASSWORD"
          fi
          temp="$temp$temp2"
      done
      sed -i -e "s/\;*session\.save_path =.*/session.save_path = \"$temp\"/g" /etc/php/"$IOFRAME_TARGET_PHP_VER"/apache2/php.ini
    else
      temp="tcp:\/\/127.0.0.1:$IOFRAME_PHP_REDIS_PORT";
      if [ -v IOFRAME_REDIS_PASSWORD ] ; then
        temp="$temp?auth=$IOFRAME_REDIS_PASSWORD"
      fi
      sed -i -e "s/\;*session\.save_path =.*/session.save_path = \"$temp \"/g" /etc/php/"$IOFRAME_TARGET_PHP_VER"/apache2/php.ini
    fi
  fi

  #Apache config
  if ! [ -v IOFRAME_NO_APACHE_CONFIG ]; then
    if ! [ -v IOFRAME_NO_ADMINER ]; then
      sudo a2enconf adminer
    fi
    sudo a2enmod rewrite
    #yes I know the following line changes some unrelated AllowOverride's to All, they are not accessible anyway so I dont care
    sed -i -e "s/AllowOverride None/AllowOverride All/g" /etc/apache2/apache2.conf
    sed -i -e "s/Options Indexes FollowSymLinks/Options FollowSymLinks Indexes/g" /etc/apache2/apache2.conf
    sed -i -e "s/SSLProtocol all/SSLProtocol all -TLSv1.2/g" /etc/apache2/mods-available/ssl.conf
    # Restart apache
    sudo systemctl reload apache2
  fi

  #Certbot stuff
  # TODO Add dynamic apache2 virtual host config - without it, it's impossible to run certbot automatically
  if ! [ -v IOFRAME_VHOST ] && ! [ -v IOFRAME_NO_CERTS ]; then
    sudo ln -s /snap/bin/certbot /usr/bin/certbot
    if ! [[ -v IOFRAME_SITE_TLDS_MULTIPLE ]]; then
      certbot --apache --noninteractive --agree-tos -m "${IOFRAME_CERTBOT_EMAIL}" -d "${IOFRAME_SITE_TLD}"
    else
      temp="";
      for TDL in $IOFRAME_SITE_TLDS_MULTIPLE
      do
          temp="$temp -d $TDL"
      done
      certbot --apache --noninteractive --agree-tos -m "${IOFRAME_CERTBOT_EMAIL}" "${temp}"
    fi
  fi

  # Finally, secure MariaDB installation (this assumes your root account uses SSH keys, not a password), and initialize stuff in it
  if ! [ -v IOFRAME_REMOTE_SQL ]; then
    # Apparently, there is no longer a reason to use secure_installation https://mariadb.com/kb/en/authentication-plugin-unix-socket/
    mysql -u root -e "SET GLOBAL log_bin_trust_function_creators = 1;"
    mysql -u root -e "SET GLOBAL time_zone = '+00:00';"
    if [ -v IOFRAME_CREATE_SQL_DB ]; then
      mysql -u root -e "CREATE DATABASE $IOFRAME_CREATE_SQL_DB;"
    fi
    if [ -v IOFRAME_SQL_USER_USERNAME ] && [ -v IOFRAME_SQL_USER_PASSWORD ]; then
      mysql -u root -e "CREATE USER '$IOFRAME_SQL_USER_USERNAME'@'%' IDENTIFIED BY '$IOFRAME_SQL_USER_PASSWORD';"
      mysql -u root -e "GRANT ALL PRIVILEGES ON * . * TO '$IOFRAME_SQL_USER_USERNAME'@'%';"
      mysql -u root -e "FLUSH PRIVILEGES;"
    fi
  fi

else

  printf "\n\n ---- TESTING ---- \n\n";
  echo "sudo apt update";
  echo "sudo apt upgrade -y";
  echo "sudo apt install -y software-properties-common dirmngr apt-transport-https wget ca-certificates";
  if ! [ -v IOFRAME_NO_PHP_INSTALL ]; then
    echo "sudo add-apt-repository -y ppa:ondrej/php";
  fi
  if ! [ -v IOFRAME_REMOTE_REDIS ]; then
    echo "sudo add-apt-repository -y ppa:redislabs/redis";
  fi
  if ! [ -v IOFRAME_REMOTE_SQL ]; then
    echo "sudo apt-key adv --fetch-keys 'https://mariadb.org/mariadb_release_signing_key.asc'";
    echo "sudo add-apt-repository -y 'deb [arch=amd64,arm64,ppc64el] https://atl.mirrors.knownhost.com/mariadb/repo/10.8/ubuntu jammy main'";
  fi
  echo "sudo apt update";
  if ! [ -v IOFRAME_NO_PHP_INSTALL ]; then
    echo "sudo apt install -y php$IOFRAME_TARGET_PHP_VER php$IOFRAME_TARGET_PHP_VER-common php$IOFRAME_TARGET_PHP_VER-dev php$IOFRAME_TARGET_PHP_VER-dom php$IOFRAME_TARGET_PHP_VER-iconv php$IOFRAME_TARGET_PHP_VER-bcmath php$IOFRAME_TARGET_PHP_VER-mysql php$IOFRAME_TARGET_PHP_VER-redis php$IOFRAME_TARGET_PHP_VER-curl php$IOFRAME_TARGET_PHP_VER-mbstring php$IOFRAME_TARGET_PHP_VER-gd php$IOFRAME_TARGET_PHP_VER-zip php-pear";
    echo "sudo update-alternatives --set php /usr/bin/php$IOFRAME_TARGET_PHP_VER";
    echo "sudo pecl channel-update pecl.php.net";
    echo "sudo pecl install redis";
  fi

  if ! [ -v IOFRAME_NO_APACHE_INSTALL ]; then
    echo "sudo apt install -y apache2";

    if [[ -v IOFRAME_OLD_PHP_VER ]]; then
      echo "sudo a2dismod $IOFRAME_OLD_PHP_VER";
    fi
    echo "sudo a2enmod php$IOFRAME_TARGET_PHP_VER";
  fi

  if ! [ -v IOFRAME_REMOTE_SQL ]; then
    echo "sudo apt install -y mariadb-server";
  fi

  if ! [ -v IOFRAME_REMOTE_REDIS ]; then
    echo "sudo apt install -y redis";
  fi

  if ! [ -v IOFRAME_NO_ADMINER ]; then
    echo "sudo apt install -y adminer";
  fi

  if ! [ -v IOFRAME_VHOST ] && ! [ -v IOFRAME_NO_CERTS ]; then
    echo "sudo curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer";
    echo "sudo apt install -y snapd";
    echo "sudo snap install --classic certbot";
  fi

  if ! [[ -v IOFRAME_NO_GIT_CLONE ]]; then

    if [[ -v IOFRAME_VHOST ]]; then
      echo "sudo git clone https://github.com/IgalOgonov/IOFrame.git /var/www/html/$IOFRAME_VHOST";
    else
      echo "sudo rm -rf /var/www/html";
      echo "sudo git clone https://github.com/IgalOgonov/IOFrame.git /var/www/html";
    fi

    echo "sudo chown -R www-data /var/www/html";

    if [[ -v IOFRAME_VHOST ]]; then
      echo "cd /var/www/html/$IOFRAME_VHOST";
    else
      echo "cd /var/www/html";
    fi
    echo "sudo composer install -q";

  fi

  if ! [ -v IOFRAME_REMOTE_REDIS ]; then
    echo "sudo systemctl enable --now redis-server";
    if [[ -v IOFRAME_REDIS_BIND_CONFIG ]]; then
      echo "sed -i -e \"s/^bind .*/bind $IOFRAME_REDIS_BIND_CONFIG/g\" /etc/redis/redis.conf";
    fi
    if [[ -v IOFRAME_REDIS_PASSWORD ]]; then
      echo "sed -i -e \"s/^\#* requirepass .*/ requirepass ${IOFRAME_REDIS_PASSWORD}/g\" /etc/redis/redis.conf";
    fi
  fi

  if ! [ -v IOFRAME_NO_PHP_CONFIG ]; then
    printf "sed -i -e 's/\;*extension=redis//g' \\ \n
              -e 's/\;extension=openssl/\;extension=openssl\\\n extension=redis /g' \\ \n
              -e 's/\;*session\.save_handler =.*/session.save_handler = redis/g' \\ \n
              -e 's/session\.gc_maxlifetime =.*/session.gc_maxlifetime = 31536000/g' \\ \n
              -e 's/\;*date.timezone =.*/date.timezone = \"UTC\"/g' \\ \n
              -e 's/upload_max_filesize =.*/upload_max_filesize = 1024M/g' \\ \n
              -e 's/post_max_size =.*/post_max_size = 1024M/g' \\ \n
              -e 's/\;*session\.cookie_samesite =.*/session.cookie_samesite = \"Strict\"/g' \\ \n
              -e 's/\;*session\.cookie_httponly =.*/session.cookie_httponly = 1/g' \\ \n
              -e 's/\;*session\.use_only_cookies =.*/session.use_only_cookies = 1/g' \\ \n
              -e 's/\;*session\.cookie_secure =.*/session.cookie_secure = 1/g' \\ \n
              /etc/php/%s/apache2/php.ini \n" "$IOFRAME_TARGET_PHP_VER";

    if [ -v IOFRAME_REMOTE_REDIS ] && [ -v IOFRAME_REDIS_BIND_CONFIG ] ; then
      temp="";
      for BIND in $IOFRAME_REDIS_BIND_CONFIG
      do
          temp2="tsp:\/\/$BIND:$IOFRAME_PHP_REDIS_PORT "
          if [ -v IOFRAME_REDIS_PASSWORD ] ; then
            temp2="$temp2?auth=$IOFRAME_REDIS_PASSWORD"
          fi
          temp="$temp$temp2"
      done
      echo "sed -i -e \"s/\;*session\.save_path =.*/session.save_path = $temp/g\" /etc/php/$IOFRAME_TARGET_PHP_VER/apache2/php.ini";
    else
      temp="tcp:\/\/127.0.0.1:$IOFRAME_PHP_REDIS_PORT";
      if [ -v IOFRAME_REDIS_PASSWORD ] ; then
        temp="$temp?auth=$IOFRAME_REDIS_PASSWORD"
      fi
      echo "sed -i -e \"s/\;*session\.save_path =.*/session.save_path = $temp /g\" /etc/php/$IOFRAME_TARGET_PHP_VER/apache2/php.ini";
    fi
  fi

  if ! [ -v IOFRAME_NO_APACHE_CONFIG ]; then
    if ! [ -v IOFRAME_NO_ADMINER ]; then
      echo "sudo a2enconf adminer";
    fi
    echo "sed -i -e \"s/AllowOverride None/AllowOverride All/g\" /etc/apache2/apache2.conf";
    echo "sed -i -e \"s/Options Indexes FollowSymLinks/Options FollowSymLinks Indexes/g\" /etc/apache2/apache2.conf";
    echo "sed -i -e \"s/SSLProtocol all/SSLProtocol all -TLSv1.2/g\" /etc/apache2/mods-available/ssl.conf";
    echo "sudo systemctl reload apache2";
  fi

  if ! [ -v IOFRAME_VHOST ] && ! [ -v IOFRAME_NO_CERTS ]; then
    echo "sudo ln -s /snap/bin/certbot /usr/bin/certbot";
    if ! [[ -v IOFRAME_SITE_TLDS_MULTIPLE ]]; then
      echo "certbot --apache --noninteractive --agree-tos -m ${IOFRAME_CERTBOT_EMAIL} -d ${IOFRAME_SITE_TLD}";
    else
      temp="";
      for TDL in $IOFRAME_SITE_TLDS_MULTIPLE
      do
          temp="$temp -d $TDL"
      done
      echo "certbot --apache --noninteractive --agree-tos -m ${IOFRAME_CERTBOT_EMAIL} ${temp}";
    fi
  fi

  if ! [ -v IOFRAME_REMOTE_SQL ]; then
    echo "mysql -u root -e \"SET GLOBAL log_bin_trust_function_creators = 1;\"";
    echo "mysql -u root -e \"SET GLOBAL time_zone = '+00:00';\"";
    if [ -v IOFRAME_CREATE_SQL_DB ]; then
      echo "mysql -u root -e \"CREATE DATABASE $IOFRAME_CREATE_SQL_DB;\"";
    fi
    if [ -v IOFRAME_SQL_USER_USERNAME ] && [ -v IOFRAME_SQL_USER_PASSWORD ]; then
      echo "mysql -u root -e \"CREATE USER '$IOFRAME_SQL_USER_USERNAME'@'%' IDENTIFIED BY '$IOFRAME_SQL_USER_PASSWORD';\"";
      echo "mysql -u root -e \"GRANT ALL PRIVILEGES ON * . * TO '$IOFRAME_SQL_USER_USERNAME'@'%';\"";
      echo "mysql -u root -e \"FLUSH PRIVILEGES;\"";
    fi
  fi

fi

# I'm regretting I didn't just write most of this in PHP and called it from bash. Pascal had better syntax than this. Thanks god PHPStorms supports this.