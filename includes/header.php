<?php
    //@session_start();
    
	include_once('utilities.php');
	
    $length = strlen(basePath);
    if( !empty($_SESSION['uid']) && (empty($_SESSION['base_Path']) || $_SESSION['base_Path']!=substr($_SERVER['SCRIPT_FILENAME'],0,$length)) ){
		$_SESSION = array();    // unset all session variables
		session_destroy();
		header('Location: login.php');
		die();
	}
	
    if ( ! headers_sent() ) { header('Content-Type: text/html; charset=utf-8'); }
    
// Initial Version: Brian Beck, 2008
    
    

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
<!-- Copyright (2008) Matrix: Michigan State University

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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 -->
    <head>
        <title>KORA</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta http-equiv="Pragma" content="no-cache" />
        <link href="<?php echo baseURI;?>css/all.css" rel="stylesheet" type="text/css" />
        <link href="includes/thickbox/thickbox.css" rel= "stylesheet" type="text/css" />
        <?php // Add style switcher code here instead of always using default
        
	if (isset($_SESSION['currentProjectStyleSheet']))
	{
		?><link href="<?php echo baseURI;?>css/<?php echo $_SESSION['currentProjectStyleSheet']?>" rel="stylesheet" type="text/css" /><?php
	}
	else
	{
        ?><link href="<?php echo baseURI;?>css/default.css" rel="stylesheet" type="text/css" /><?php
    } ?>
		<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script type="text/javascript" src="includes/thickbox/thickbox.js"><</script>
		
		<script language="javascript" type="text/javascript" >
<!-- hide

function jumpto(x){

if (document.language.languageselect.value != "null") {
document.language.submit();
}
}

// end hide -->
</script>
<link rel="shortcut icon" type="image/x-icon" href="<?php echo baseURI;?>favicon.ico">
    </head>
    <body>
		<div id="container_main">
		<div id="container">
		<div id="header">
        <div id="login">
        
        <?php if (!isLoggedIn()) { ?>
            
            <a href="<?php echo baseURI;?>index.php"><?php echo gettext('Log In');?></a> |
            <a href="<?php echo baseURI;?>register.php"><?php echo gettext('Register');?></a> |
            <a href="<?php echo baseURI;?>activate.php"><?php echo gettext('Activate Account');?></a>
        <?php } else { ?>
        	<a href="<?php echo baseURI;?>logout.php"><?php echo gettext('Log Out');?></a> |
            <a href="<?php echo baseURI;?>accountSettings.php"><?php echo gettext('Update User Info')?></a>
        <?php } ?>

        </div>
		<?php if (isLoggedIn()) { ?>
		<div class="clear"></div>

		<form id="viewobject" action="viewObject.php" method="get">View Record:&nbsp<input type="text" name="rid" /></form>
		<?php } ?>
        </div>
		<div id="content_container">
<?php require('menu.php'); ?>
<div id="right_container"><div id="right">
<?php
// See if the database is up-to-date
if (isset($_SESSION['dbVersion']) && isSystemAdmin() && version_compare($_SESSION['dbVersion'], LATEST_DB_VERSION, '<'))
{
	echo '<div class="error">'.gettext('Your database is out of date; please upgrade it').'<br /></div>';
}

if(isset($_SESSION['currentProjectName'])){
	echo '<a href="selectScheme.php">'.$_SESSION['currentProjectName'].'</a>';
}
if(isset($_SESSION['currentSchemeName'])){
	echo '&mdash;&gt;<a href="schemeLayout.php">'.$_SESSION['currentSchemeName'].'</a>';
}
if(isset($_REQUEST['rid'])){
	echo '&mdash;&gt;<a href="viewObject.php?rid='.$_REQUEST['rid'].'">'.$_REQUEST['rid'].'</a>';
}
if(isset($_SESSION['currentProjectName'])){
	echo '<br/>';
}
?>
