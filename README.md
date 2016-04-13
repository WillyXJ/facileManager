facileManager
=============
                                          
(c) 2013-2016 by facileManager project members.
facileManager is free software released under the terms of GNU GPL v2.
Please see LICENSE for license.

Official site: http://www.facilemanager.com

Official git repository: https://github.com/WillyXJ/facileManager


Installation instructions for facileManager
===========================================

This document describes the necessary steps to install facileManager and get it
to a working state - it shouldn't take long at all!

There are two parts: the server and the client(s).  The server is where the web
interface will run from.  It is **_not_** required to host the MySQL database on the
same server as the web interface.  The client runs on the servers to manage.

Please note: Internet Explorer is not supported - it's too tiresome.

Prerequisites
-------------

facileManager (server) requires the following:

* PHP 5.2.0 or later with MySQL support
* MySQL 5.0 or later
  * Required MySQL user privileges on the database include 
   `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, LOCK TABLES`
* A working webserver (httpd) with mod_rewrite.so enabled
* facileManager-core
* JavaScript enabled in your web browser

fM client requires the following:

* ISC BIND 9.3 or later (for the fmDNS module only)
* PHP 5.0 or later
* A running web server if using http(s) update methods
  * The install script supports the following web servers:
    * httpd


Server Installation
-------------------

1. Move the contents of the server directory to your document root.
   (ie /var/www/html/facileManager/)
2. Point your web browser to http://example.com/facileManager/ or your
   virtualhost if you set one up (ie http://facileManager.example.com).
3. Follow the installation wizard to setup your database.

Additional Steps (OS-based)
---------------------------

The following steps need to be performed on vanilla installations of certain 
Operating Systems to allow .htaccess files to be used.

### Debian-based/Ubuntu - 
> Edit /etc/apache2/sites-enabled/default and change 'AllowOverride 
> None' to 'AllowOverride All' under <directory /var/www/> and reload 
> apache.

### RHEL7/CentOS7 - 
> Edit /etc/httpd/conf/httpd.conf and change 'AllowOverride 
> None' to 'AllowOverride All' under <Directory /var/www/html> and reload 
> apache.


Client Installation
-------------------

1. Move the contents of the client directory to /usr/local/ on your client
   servers to manage. (Note: client files from the core (or complete) package
   are also required.)
   `sudo mv facileManager/client/facileManager /usr/local/`
2. For each module you wish to use, run the following to complete the client
   installation.
   `sudo php /usr/local/facileManager/<module_name>/client.php install`
	


Upgrade instructions for facileManager
======================================

This section describes the necessary steps to upgrade facileManager and get it
to a working state - it shouldn't take long at all!


Server Upgrade
--------------

1. Make a backup of your database using the built-in tool via the UI or manually.
2. Make a backup of your config.inc.php file.
3. Delete your old facileManager files.
4. Extract/upload the new files from the server directory.
5. Copy your backup of config.inc.php to the document root.
   (ie /var/www/html/facileManager/)
6. Login as a super-admin to facileManager and follow the wizard to upgrade 
   your database.
7. Once fM is upgraded, you will be redirected to the admin-modules page where
   you can upgrade your modules individually.


Client Upgrade
--------------

1. Make a backup of your config.inc.php file.
2. Move the contents of the client directory to /usr/local/ on your client
   servers to manage.
   `sudo mv facileManager/client/facileManager /usr/local/`
3. Restore your backup of config.inc.php to /usr/local/facileManager.

Alternatively, since v1.1, you can update the clients through the UI (servers
page) or by running the following on the clients:

`sudo php /usr/local/facileManager/<module_name>/client.php upgrade`


Important Upgrade Notes
-----------------------

The client files have been consolidated and standardized starting with versions
2.2 and 1.3 of fmDNS and fmFirewall respectively which cleans up some files and
a lot of code to put the project in a better position going forward.

When upgrading from fmDNS <= 2.1.x or fmFirewall <= 1.2.x, remove the old client
files with the following:

`sudo rm -rf /usr/local/facileManager/*/{dns,fw}.php /usr/local/facileManager/*/www`

You MUST also update the following based on your client's update method.

1. Cron - update root's crontab to use client.php instead of dns.php or fw.php
2. SSH  - update your sudoers file to use client.php instead of dns.php or fw.php
3. HTTP - update your sudoers file to use client.php instead of dns.php or fw.php

          and update the module symlink in your document root to use the www  
          directory from the core files instead of the module directory.  

          For example:  

          sudo rm /var/www/html/<module_name>  
          sudo ln -sf /usr/local/facileManager/www /var/www/html/fM  

Alternatively, you can run the reinstall script which will ensure the proper
files and configs will be in place, but it will not remove the old sudoers entries
and document root symlinks.

`sudo php /usr/local/facileManager/<module_name>/client.php reinstall`
