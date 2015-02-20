<?php
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

// Initial Version: Brian Beck, 2008
// Refactor: Joe Deming, Anthony D'Onofrio 2013

require_once('includes/includes.php');

Manager::Init();

if (Manager::IsLoggedIn())
{
	// already logged in, so redirect to the index
	header('Location: index.php');
	die();
}

Manager::PrintHeader();

//This div is display a firefox bug quick-fix
$firefox_error="<div id='firefox_Kora'>
	<p style='color:red'>NOTICE FIREFOX USERS: Firefox disables auto-refreshing by default, a feature used in Kora 2.6.1. This feature affects the 
	ability for users to update their profile and to ingest new records.</p>
	<br />
	We are working diligently to remove auto-refreshing from Kora to support Firefox out of the box. To use Kora now, please:
	<br />
	(1) Enter 'about:config' into Firefox's navigation bar<br />
	(2) Search for accessibility.blockautorefresh<br />
	(3) Modify its value to 'true'
	<br /><br />
	Thank you for your understanding,
	<br /><br />
	Anthony D'Onofrio<br />
	Kora Dev Team<br />
	matrix@msu.edu
</div>";
$agent = "";
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $agent = $_SERVER['HTTP_USER_AGENT'];
}
if (strlen(strstr($agent, 'Firefox')) > 0) {
    echo $firefox_error;
}

User::PrintLoginForm();

Manager::PrintFooter();

?>
