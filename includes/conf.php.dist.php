<?php

// Copy the contents of this file into a file called conf.php

/**
Copyright (2008) Matrix: Michigan State University

This file is part of KORA.

KORA is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

KORA is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>. */

$dbuser = "";                // the user to connect to the database
$dbpass = "";                // the password to connect to the database
$dbname = "";                // the database to connect to
$dbhost = "";   // the server the database is on

// The Root URL for this KORA installation - trailing slash required
define('baseURI', '');
// The Root (server) Path for this KORA installation - trailing slash required
define('basePath', '');
// The E-Mail address that Activation E-Mails should be listed as being from
define('baseEmail', 'kora@');
// The subdirectory files should be stored in
define('fileDir', 'files/');
// The subdirectory extracted files are stored in temporarily
define('extractFileDir', fileDir.'extractedFiles/');
// the subdirectory that files waiting for approval by a moderator are stored in
define('awaitingApprovalFileDir', fileDir.'awaitingApproval/');
// the number of seconds before the session times out and users are forced to log in again.
define('sessionTimeout',30*60);

// the number of results in a single search results page
define('RESULTS_IN_PAGE', 10);

// the URL for Solr
define('solr_url', '');
// whether or not Solr is enabled
$solr_enabled = false;


require_once("required.php");

?>
