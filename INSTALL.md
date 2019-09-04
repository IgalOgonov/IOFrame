# Installation  
There are three parts to the installation:  
1) Install all dependencies
2) Configure all dependencies
3) Run the IOFrame installer 

In this section, I will describe how to do all 3, on Windows or Linux (Ubuntu 18).

## Dependencies  

**PHP 5.6-7.3+**  
**Apache2**  
**MySQL 5.7-8, MariaDB 10.3++**  
**Redis 4+**  

### Windows
On windows, this is a bit simpler.  

**Apache, PHP & MySQL (including PHPMyAdmin)**  
Simply install [WAMP](http://www.wampserver.com/en/).  
That takes care of everything required, and lets you choose between MariaDB and MySQL (albait an old version).  

**Redis - Part 1**  [OPTIONAL]  
If you want to use Redis (and you really should, performance wise), go [over here](herehttps://pecl.php.net/package/redis), download the latest dll (as of writing this, it's [4.3](https://pecl.php.net/package/redis/4.3.0/windows)), download the Thread safe x64 version, and throw it over at C:\wamp64\bin\php\php<Your selected version>\ext. 
I will explain below how to configure PHP to use it, or you can use the instructions on the github.  

**Redis - Part 2**  [OPTIONAL]  
..now, you want to have an actual redis server running on your machine.  
Unless you have a remote one ready, you should head over to [redis-windows](https://github.com/ServiceStack/redis-windows/) and follow the instructions. The best option is one of the first 2.

**IOFrame**  
Download and extract (or use git to clone) IOFrame into C:\wamp64\www (or whatever your server root is).

### Linux

**Apache**  
Install following [this guide](https://help.ubuntu.com/lts/serverguide/httpd.html). Refer to it in case you need help understanding the configurations.

**PHP**  
Install 7.3+ following [this guide](https://thishosting.rocks/install-php-on-ubuntu/), or whichever other one you find.  
The main thing to know is that you need to install some important PHP modules like php7.3-curl on your own. The above guide covers important PHP 7.3 modules as well.  
As far as I recall, the important modules tthat aren't default are:  
PDO, and cURL. 
The redis module is also important, but optional, and is described later.

**MySQL/MariaDB**  
Install MariaDB [from here](https://linuxize.com/post/how-to-install-mariadb-on-ubuntu-18-04/) or MySQL [from here](https://help.ubuntu.com/lts/serverguide/mysql.html). You might refer to those links if you need help understanding some of the basic config.

**PHPMyAdmin**  [OPTIONAL]  
While not required, it is extremely useful and is used in a low of examples here (as well as a general DB management tool).  
[This guide](https://tecadmin.net/install-phpmyadmin-in-ubuntu/) seems to cover it well enough.  

**Redis** [OPTIONAL]  
Highly recommended in any production system, as it affects performance drastically, and you cannot run proper multi-node setups without a redis node to centralize the user sessions.  
[This guide](https://tecadmin.net/install-redis-ubuntu/) seems to show how easy it is to install Redis as well as PHPredis on Linux.  
However, I myself installed php-redis through pecl, by running   
    
    pecl install redis

Once you install it, make sure to test it is running properly using redis-cli.

**IOFrame**  
Go to your server root (default /var/www/html), and run  

    sudo git clone https://github.com/IgalOgonov/IOFrame.git

## Configurations  - Windows & Linux
**PHP**  
The framework allows you to control your session lifetime. However, the PHP setting may always through it out before your own value, if the PHP value is lower. Go over to C:\wamp64\bin\php\php<version> on Windows, etc/php/<version>/apache2/php.ini on Linux, open php.ini, and edit the line

    session.gc_maxlifetime = <Something>

to be your desired value in seconds.  

Also, if you want your maximum file size be controlled by IOFrame, change 
	
	upload_max_filesize = <some very big number>
	AND 
	post_max_size = <some very big number>

Restart Apache.

Again, if your value in the IOFrame settings is HIGHER than this, it will get discarded by PHP first.


**MySQL/MariaDB**  
Apart from other configs, you need go to the concole and execute  

    SET GLOBAL log_bin_trust_function_creators = 1;
    
Without it, installation will fail to create the needed stored procedures on newer versions.

Also, create a DB and a user with full permissions in it - remember their names.

**Redis**  [OPTIONAL]  
Go over to C:\wamp64\bin\php\php<version> on Windows, etc/php/<version>/apache2/php.ini on Linux, open php.ini, and add extension=php_redis at the bottom of the extension=<something> list.
Now it looks like:

    ...
    extension=xsl
    ;extension=zend_test
    extension=php_redis
    ...

You'll probably want the PHP session to be saved on redis, too.  

Edit the following lines:

    ;session.save_handler = files (comment out)
    session.save_handler = redis (add)
    ;session.save_path ="e:/wamp64/tmp"(comment out)
    session.save_path ="127.0.0.1:6379"(add)
    ;session.save_handler = files (comment out)
    session.save_handler = redis (add)
    ...
    
Restart Apache.
    
**File System [Linux Only]**  
Without permissions, the IOFrame installer will fail to create the new folders needed during its operations. The simplest solution is:  
Go to the folder you cloned IOFrame to, and run  

    chmod 777 IOFrame (Or whichever name you cloned it under)
    
There are probably safer ways to make it work, but this is the fastest, and a threat model where an attacker who compromises your linux VM / Server compromises the whole system is not irrational.  
Alternatively, you can change ownership of the folder to the PHP user. A simple guide/answer on how to do it can be found [here](https://stackoverflow.com/questions/2900690/how-do-i-give-php-write-access-to-a-directory#2900727)

**Apache**  
In order for the routing to work, the following must be true:  

1. Apache has "rewrite_module" enabled.  
This can be enabled in WAMP64->Apache->Modules on windows,  
or by running the following on Linux: 

    sudo a2enmod rewrite
    sudo service apache2 restart
	
2. You need to set "RewriteEngine On" and "Options FollowSymLinks" must be enabled.
Those can be found in the Apache configuration directory, typically  

    C:\wamp64\bin\apache\apache2.4.39\conf on windows, 
or in 

    /etc/apache2 
on linux.

Without it, expect the router to fail, and the framework api calls fail with it.

## Installation

Before you can do anything else, you need to clone the actual framework onto your PC.
Clone the latest version from [the GitHub repo](https://github.com/IgalOgonov/IOFrame) into your server root - so, for example, the files lie at C:\wamp64\www\IOFrame.  
Once that is done, you need to prepare the following things:  
- Your MySQL Address (Default 127.0.0.1), Port (Default 3306 for MySQL, 3307 for MariaDB), DB name, Username, Password (one you should have created those at the configuration step).  
- Your redis Address (Default 127.0.0.1), Port (Default 6379), and password if you set one.
- [OPTIONAL] SMTP credentials - without them, you wont be able to send mail. Read more about gmail ones [in here](https://www.lifewire.com/what-are-the-gmail-smtp-settings-1170854). Note if you are hosting your server on a cheap c-panel host, you may simply create an SMTP account there.

Now, open your browser, and go to http://127.0.0.1/IOFrame/_install.php (replace "IOFrame" with path/from/server/root if you didn't just clone the repo there).  

**Stage 1**  
![Stage 1](front/ioframe/img/docs/installScreenshots/1.png)  
At this stage, you merely choose the name of the site. It will be used in a few places, such as default mail templates you send.  
 
**Stage 2**  
![Stage 2](front/ioframe/img/docs/installScreenshots/2.png)  
Lots of settings here. They are all explained, but I need to emphesize - WRITE DOWN AND KEEP THE PRIVATE KEY IN A SECURE PLACE WHERE YOU WONT LOSE IT.  
 
**Stage 3**  
![Stage 3](front/ioframe/img/docs/installScreenshots/3.png)  
Configurations for the Redis handler. Apart from the obvious ones:  
* "Timeout" is the timeout before the handler will stop trying to connect to an unreachable server.
* "Presistent connection" is explained over at the PHPRedis github page.
 
**Stage 4**  
![Stage 4](front/ioframe/img/docs/installScreenshots/4.png)  
Everything except two things were explained earlier:  
* Table Prefix - an optional table prefix, always trimmed to 6 characters - here in case you are installing an instance of the framework on a DB that contains other tables.
* Safe DB Mode - Just leave this unchecked, otherwise during each Create/Update/Delete query your whole DB will wait for it to complete before letting the other queries to execute. And if you are running more than 1 node, this is just useless.  
 
**Stage 5**  
![Stage 5](front/ioframe/img/docs/installScreenshots/5.png)  
At this stage, will try to connect to the DB with the given settings.  
If the output at the bottom is "All is well", the connection was successful. Otherwise, it failed, and the error will be displayed.  
In the latter case, go back check that the DB is properly installed/configured, and that your user/password/db/address/port were correct.
 
**Stage 6**  
![Stage 6](front/ioframe/img/docs/installScreenshots/6.png)  
Database initiation happens here.  
If the bottom of the output reads "Database Initiated", and there is no mention of errors, it means all went well.  
If there are some errors, consider dropping the database from PHPMyAdmin and trying again (or checking the configurations mentioned earlier in this post).
 
**Stage 7**  
![Stage 7](front/ioframe/img/docs/installScreenshots/7.png)  
A few database tables are initiated here, after the previous stage was confirmed successful.  
If you have your SMTP settings prepared, input them here, else just press next.  
 
**Stage 8**  
![Stage 8](front/ioframe/img/docs/installScreenshots/8.png)  
Here, create your admin account. The username/password/mail provided here are not validated, so make sure the password matches the restrictions of the system (hover over that ? mark to see the default ones). 
 
**Stage 9**  
![Stage 9](front/ioframe/img/docs/installScreenshots/9.png)  
You are finished, now you will be taken to the admin panel, and should be able to log in with the user you created at the last stage.
 
