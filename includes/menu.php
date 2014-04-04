<?php 
/*
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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Initial Version: Brian Beck, 2008
// Revised: Cassia Miller, 2009 (added internationalization support)

require_once('utilities.php');

	// Set the language if it's been passed
	if (isset($_POST['languageselect'])) {
	
		// for the love of god validate this against a whitelist
		// so I can't set the language to "Klingon" and blow up the site
		// -B. Beck 10/2/09
	
		$_SESSION['language'] = $_POST['languageselect'];
	}

include('gettextSupport.php');	// need to include this to access $locale_list
?>

<?php  $path_parts = pathinfo($_SERVER["PHP_SELF"]);?>

<div id="left">
     
    <?php  if (!isLoggedIn()) { ?>
    <div class="ddblueblockmenu">
	<div class="menutitle"><?php echo gettext('Accounts');?></div>
    <ul>
        <li><a class="<?php  if($path_parts['basename']=="index.php") print "selected"; else print "normal" ?>" href="index.php"><?php echo gettext('Log In');?></a></li>
	<li><a class="<?php  if($path_parts['basename']=="register.php") print "selected"; else print "normal" ?>" href="register.php"><?php echo gettext('Register an Account');?></a></li>
        <li><a class="<?php  if($path_parts['basename']=="activate.php") print "selected"; else print "normal" ?>" href="activate.php"><?php echo gettext('Activate an Account');?></a></li>
    </ul>
    <div class="language"><h3><?php echo gettext('change language');?></h3>
    <form enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']?>" method="post" name="language">
    <select name="languageselect" onchange="jumpto(document.language.languageselect.options[document.language.languageselect.options.selectedIndex].value)">
    <?php 
    
    // If a language isn't set, set it to the browser language if possible.
    if(!isset($_SESSION['language'])) 
    {
    	$client_lang = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,5);
    	if(stristr($client_lang, '-')) $client_lang = str_ireplace('-', '_', $client_lang);
    	if(array_key_exists($client_lang, $locale_list)) $_SESSION['language']=$client_lang;
    }
    

	
    // sticky drop down menu to choose the display language
    foreach($locale_list as $key => $value)
    	  {
    		echo "<option value=\"$key\"";
    		if(isset($_SESSION['language']) && ($key == $_SESSION['language'])) echo " selected";
    		echo ">$value</option>";
    	  }?>	     
    </select>
    </form>
    </div>
    </div> 

    <?php  }//end isLoggedIn

    else { ?>
    
    <?php  if (isSystemAdmin()) { ?>
	    <div class="ddblueblockmenu">
	<div class="menutitle"><?php echo gettext('Management');?></div>

    <ul>
   	<li><a class="<?php  if($path_parts['basename']=="manageProjects.php") print "selected"; else print "normal" ?>" href="manageProjects.php"><?php echo gettext('Create/Modify Projects');?></a></li>
	<li><a class="<?php  if($path_parts['basename']=="manageSearchTokens.php") print "selected"; else print "normal" ?>" href="manageSearchTokens.php"><?php echo gettext('Manage Search Tokens');?></a></li>
	<li><a class="<?php  if($path_parts['basename']=="manageUsers.php") print "selected"; else print "normal" ?>" href="manageUsers.php"><?php echo gettext('Manage Users');?></a></li>
    <li><a class="<?php  if($path_parts['basename']=="systemManagement.php") print "selected"; else print "normal" ?>" href="systemManagement.php"><?php echo gettext('System Management');?></a></li>
<?php  /* ?>	<li><a class="<?php  if($path_parts['basename']=="searchEventLog.php") print "selected"; else print "normal" ?>" href="searchEventLog.php"><?php echo gettext('Search Event Log');?></a></li> <?php  */ ?>
    </ul>
    </div>
    <?php  }//end isSystemAdmin ?>
	
    <?php  }//end else ?>

<?php  if (isLoggedIn()) { 

// get the User's Permissions for the current Project
$userPermissions = getUserPermissions();
    
?>
    <div class="ddblueblockmenu">
	<div class="menutitle"><?php echo gettext('Search');?></div>
	<ul>
        <?php
        if(@$solr_enabled)
        {
            echo "<li><a class=\"";
            if($path_parts['basename']=="koraFileSearch.php") echo "selected";
            else echo "normal";
            echo "\" href=\"koraFileSearch.php\">KORA File Search</a></li>";
        }
        ?>
<?php   if (isset($_SESSION['currentProject']) && !empty($_SESSION['currentProject'])) { ?>
<?php if(isset($_SESSION['currentScheme']) && !empty($_SESSION['currentScheme']) ) {?>
		<li><a class="<?php  if($path_parts['basename']=="advancedSearch.php") print "selected"; else print "normal" ?>" href="advancedSearch.php"><?php echo gettext('Advanced Search within Scheme');?></a></li>
<?php 	  }
	} ?>
		<li><a class="<?php  if($path_parts['basename']=="crossProjectSearch.php") print "selected"; else print "normal" ?>" href="crossProjectSearch.php"><?php echo gettext('Cross-Project Search');?></a></li>
<?php   if (isset($_SESSION['currentProject']) && !empty($_SESSION['currentProject'])) { ?>
		<li><a class="<?php  if($path_parts['basename']=="searchProject.php") print "selected"; else print "normal" ?>" href="searchProject.php"><?php echo gettext('Search within Project');?></a></li>
		
<?php }//end current scheme?>
	</ul>
	</div>
<?php 	if (isset($_SESSION['currentScheme']) && !empty($_SESSION['currentScheme'])) { ?>
    <div class="ddblueblockmenu">
	<div class="menutitle"><?php echo gettext('Record');?></div>
	<ul>
<?php if (isProjectAdmin()){//This also checks if they're systemAdmin
		echo '<li><a class="normal" href="schemeExportLanding.php">'.gettext('Export Data To XML').'</a></li>'; 
	}?>
    <li><a class="<?php if($path_parts['basename']=="importMultipleRecords.php") print "selected"; else print "normal" ?>" href="importMultipleRecords.php?pid=<?php echo $_SESSION['currentProject']?>&amp;sid=<?php echo $_SESSION['currentScheme']?>"><?php echo gettext('Import Records from XML');?></a></li>
<?php   
		if ( ($userPermissions & (PROJECT_ADMIN | INGEST_RECORD)) || isSystemAdmin() ) {  ?>	
		<li><a class="<?php if($path_parts['basename']=="ingestObject.php") print "selected"; else print "normal" ?>" href="ingestObject.php"><?php echo gettext('Ingest Record');?></a></li>
<?php }//endif isSystemAdmin ?>
        <li><a class="<?php if($path_parts['basename']=="searchProjectResults.php") print "selected"; else print "normal" ?>" href="searchProjectResults.php?pid=<?php echo $_SESSION['currentProject']?>&amp;sid=<?php echo $_SESSION['currentScheme']?>"><?php echo gettext('List Scheme Records');?></a></li>		
        
		
		<li><a class="<?php if($path_parts['basename']=="reviewPublicIngestions.php") print "selected"; else print "normal" ?>" href="reviewPublicIngestions.php"><?php echo gettext('Review Public Ingestions');?></a></li>
		
	</ul>
    </div>
<?php } // endif canIngest ?>


<?php   if (isset($_SESSION['currentProject']) && !empty($_SESSION['currentProject'])) { ?>
    <div class="ddblueblockmenu">
	<div class="menutitle"><?php echo gettext('Scheme');?></div>
    <ul>
    <?php   if (($userPermissions & (PROJECT_ADMIN | CREATE_SCHEME))  || isSystemAdmin()) { ?>
		<li><a class="<?php if($path_parts['basename']=="addScheme.php") print "selected"; else print "normal" ?>" href="addScheme.php"><?php echo gettext('Add a New Scheme');?></a></li>
		<li><a class="<?php if($path_parts['basename']=="importScheme.php")print "selected"; else print "normal" ?>" href="importScheme.php"><?php echo gettext('Import Scheme From XML');?></a></li>
<?php  }?>
<?php  if(($userPermissions & (PROJECT_ADMIN | EDIT_LAYOUT)) || isSystemAdmin()) {?>
<?php  if (isset($_SESSION['currentScheme']) && !empty($_SESSION['currentScheme'])) { ?>
        <li><a class="<?php if($path_parts['basename']=="manageAssocPerms.php") print "selected"; else print "normal" ?>" href="manageAssocPerms.php"><?php echo gettext('Manage Associator Permissions');?></a></li>
<?//php }?>
        <li><a class="<?php if($path_parts['basename']=="manageRecordPresets.php") print "selected"; else print "normal" ?>" href="manageRecordPresets.php"><?php echo gettext('Manage Record Presets');?></a></li>        
<?php }}
	   if (isSystemAdmin()) { 
	   if (isset($_SESSION['currentScheme']) && !empty($_SESSION['currentScheme'])) {
	   	?> 
        <li><a class="<?php if($path_parts['basename']=="updateDublinCore.php") print "selected"; else print "normal" ?>" href="updateDublinCore.php"><?php echo gettext('Refresh Dublin Core Data');?></a></li>
<?php   // endif isProjectAdmin ?>
<?php  if(($userPermissions & (PROJECT_ADMIN | EDIT_LAYOUT)) || isSystemAdmin()) {?>
        <li><a class="<?php if($path_parts['basename']=="manageDublinCore.php") print "selected"; else print "normal" ?>" href="manageDublinCore.php"><?php echo gettext('Manage Dublin Core Fields');?></a></li>
<?php  } }}?>
<?php  if (isset($_SESSION['currentScheme']) && !empty($_SESSION['currentScheme'])) { ?>
		<li><a class="<?php if($path_parts['basename']=="schemeLayout.php") print "selected"; else print "normal" ?>" href="schemeLayout.php"><?php echo gettext('Scheme Layout');?></a></li>
<?php /* if (($userPermissions & (PROJECT_ADMIN | CREATE_SCHEME))  || isSystemAdmin()) { ?>
		<li><a class="<?php if($path_parts['basename']=="importScheme.php") print "selected"; else print "normal" ?>" href="importScheme.php">Import a Scheme</a></li>
<?php  } // endif isProjectAdmin ?>
<?php  if (($userPermissions & (PROJECT_ADMIN | EXPORT_SCHEME))  || isSystemAdmin()) { ?>
		<li><a class="<?php if($path_parts['basename']=="exportScheme.php") print "selected"; else print "normal" ?>" href="exportScheme.php">Export a Scheme</a></li>
<?php  } // endif isProjectAdmin ?>
<?php */ 
  } // endif currentScheme ?>
  	<li><a class="<?php  if($path_parts['basename']=="selectScheme.php") print "selected"; else print "normal" ?>" href="selectScheme.php"><?php echo gettext('Select a Scheme');?></a></li>
    </ul></div>
<?php  } // endif currentProject?>

 <div class="ddblueblockmenu">
	<div class="menutitle"><?php echo gettext('Project');?></div>    
    <ul>
    <?php  if (isset($_SESSION['currentProject']) && (($userPermissions & PROJECT_ADMIN) || isSystemAdmin())) { ?>
    <li><a class="<?php  if($path_parts['basename']=="manageControlPresets.php") print "selected"; else print "normal" ?>" href="manageControlPresets.php"><?php echo gettext('Manage Control Presets');?></a></li>
    <li><a class="<?php  if($path_parts['basename']=="manageGroups.php") print "selected"; else print "normal" ?>" href="manageGroups.php"><?php echo gettext('Manage Groups');?></a></li>
    <li><a class="<?php  if($path_parts['basename']=="manageProjectUsers.php") print "selected"; else print "normal" ?>" href="manageProjectUsers.php"><?php echo gettext('Manage Project Users');?></a></li>
    <?php  } ?>
    <li><a class="<?php  if($path_parts['basename']=="selectProject.php") print "selected"; else print "normal" ?>" href="selectProject.php"><?php echo gettext('Select a Project');?></a></li>
    </ul>
    </div>


<?php } // endif Loggedin ?>

</div> <!-- left -->
