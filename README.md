![KORA logo](http://kora.matrix.msu.edu/korapromoimages/newkora_logo.png "kora logo")


KORA
====

KORA Installation Instructions

Requirements:

KORA requires a web server, PHP 5.1.3 or newer, MySQL 5.0.3 or newer, and a mail transfer agent that the PHP mail() call will utilize for sending e-mail about account activation.

To install KORA do the following:

1. Move the KORA folder to a web accesible location
2. Make sure the web server can write to the includes directory
3. Navigate to install_path/install/databaseInstall.php in your web browser
4. Fill out all the information and click the 'generate configuration' box, click submit
5. Check in includes/conf.php to verify that the settings are correct
6. Log in to KORA using your web browser.  You can use the koraadmin user and the password you chose (or generated) for you.  Make sure you make a user and fix any problems with your MTA before you continue using the system.  KORA must be able to send email in order for new
   users to register.
7. Delete the install directory.
8. Remove write permissions from the includes directory for the web server.

To upgrade KORA from a previous version, see the instructions located at install_path/install/readme.

KORA is now ready to use.  Please see the user documentation in the install_path/docs/ directory for information on how to use KORA.
