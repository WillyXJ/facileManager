facileManager
=============
                                          
(c) by facileManager project members.
facileManager is free software released under the terms of GNU GPL v2.
Please see LICENSE for license.

Official site: https://www.facilemanager.com

Official git repository: https://github.com/WillyXJ/facileManager

Official documentation: https://docs.facilemanager.com


Installation instructions for facileManager
===========================================

This document describes the necessary steps to install facileManager and get it
to a working state - it shouldn't take long at all!

There are two parts: the server and the client(s).  The server is where the web
interface will run from.  It is **_not_** required to host the MySQL database on the
same server as the web interface.  The client runs on the servers to manage.

Please note: Internet Explorer is not supported - it's too tiresome.

[Minimum browser versions](https://developer.mozilla.org/en-US/docs/Web/CSS/color_value/light-dark#browser_compatibility)

Prerequisites
-------------

facileManager (server) requires the following:

* PHP 7.3.0+ with MySQL support
* MySQL 5.0 or later
  * Required MySQL user privileges on the database include

   `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, LOCK TABLES`

* A working webserver (httpd) with mod_rewrite.so enabled
* facileManager-core
* JavaScript enabled in your web browser

fM client requires the following:

* ISC BIND 9.3 or later (for the fmDNS module only)
* PHP 5.0+
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
> None' to 'AllowOverride All' under `<directory /var/www/>` and reload 
> apache.

### RHEL7/CentOS7 - 
> Edit /etc/httpd/conf/httpd.conf and change 'AllowOverride 
> None' to 'AllowOverride All' under `<Directory /var/www/html>` and reload 
> apache.


Client Installation
-------------------

1. Move the contents of the client directory to /usr/local/ on your client
   servers to manage. (Note: client files from the core (or complete) package
   are also required.)

   `sudo mv facileManager/client/facileManager /usr/local/`

2. For each module you wish to use, run the following on each client to complete
   the installation.

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

Alternatively, since v3.0, you can update the server through the UI (modules
page).  You should still backup your database though!


Client Upgrade
--------------

1. Make a backup of your config.inc.php file.

   `cp /usr/local/facileManager/config.inc.php .`

2. Move the contents of the client directory to /usr/local/ on your client
   servers to manage.

   `sudo mv facileManager/client/facileManager /usr/local/`

3. Restore your backup of config.inc.php to /usr/local/facileManager.

   `sudo mv config.inc.php /usr/local/facileManager`

Alternatively, since v1.1, you can update the clients through the UI (servers
page) or by running the following on the clients:

`sudo php /usr/local/facileManager/<module_name>/client.php upgrade`
