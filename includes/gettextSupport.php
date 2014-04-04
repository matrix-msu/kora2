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

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

 * In the messages.po files, the date formats ("MM DD, YYYY", etc.) must stay
 * in the same format. The letters may change according to language but they 
 * must stay in the same positions.

   When creating a new message.po file with xgettext -n *.php, 
 * 		you must list the following files:  ((relative to any LC_MESSAGES folder))
 * 			../../../controls/associatorControl.php
 * 			../../../controls/dateControl.php
 * 			../../../controls/fileControl.php
 * 			../../../controls/geolocatorControl.php
 * 			../../../controls/imageControl.php
 * 			../../../controls/listControl.php
 * 			../../../controls/multiDateControl.php
 * 			../../../controls/multiListControl.php
 * 			../../../controls/multiTextControl.php
 * 			../../../controls/textControl.php
 *			../../../includes/dublinFunctions.php
 *			../../../includes/fixity.php
 * 			../../../includes/footer.php
 * 			../../../includes/header.php
 *			../../../includes/ingestionClass.php
 *			../../../includes/koraSearch.php
 * 			../../../includes/menu.php
 *			../../../includes/presetFunctions.php
 *			../../../includes/projectFunctions.php
 *			../../../includes/required.php
 *			../../../includes/schemeFunctions.php
 *			../../../includes/searchTokenFunctions.php
 *			../../../includes/userFunctions.php
 *			../../../includes/utilities.php
 *			../../../accountSettings.php
 *			../../../activate.php
 *			../../../addCollection.php
 *			../../../addControl.php
 *			../../../addScheme.php
 *			../../../assocSearch.php
 *			../../../crossProjectSearch.php
 *			../../../crossProjectSearchResults.php
 *			../../../deleteObject.php
 *			../../../editCollection.php
 *			../../../editControl.php
 *			../../../editOptions.php
 *			../../../editScheme.php
 * 			../../../importMultipleRecords.php
 * 			../../../importScheme.php
 *			../../../index.php
 * 			../../../ingestApprovedData.php
 *			../../../ingestObject.php
 * 			../../../login.php
 * 			../../../manageAssocPerms.php
 * 			../../../manageControlPresets.php
 * 			../../../manageDublinCore.php
 * 			../../../manageGroups.php
 * 			../../../manageProjects.php
 * 			../../../manageProjectUsers.php
 * 			../../../manageRecordPresets.php
 * 			../../../manageSearchTokens.php
 * 			../../../manageUsers.php
 * 			../../../publicIngest.php
 *  		../../../recoverPassword.php
 * 			../../../register.php
 * 			../../../resetPassword.php
 *  		../../../reviewPublicIngestions.php
 *  		../../../schemeExport.php
 *  		../../../schemeExportLanding.php
 * 			../../../schemeLayout.php
 * 			../../../searchProject.php
 * 			../../../searchProjectResults.php
 * 			../../../selectProject.php
 * 			../../../selectScheme.php
 * 			../../../systemManagement.php
 * 			../../../updateDublinCore.php
 *			../../../upgradeDatabase.php
 *			../../../uploadXML.php
 *			../../../viewObject.php
 *
 *		Here is the list again for pasting in the terminal. (see the wiki)
		
 ../../../controls/associatorControl.php ../../../controls/dateControl.php ../../../controls/fileControl.php ../../../controls/geolocatorControl.php ../../../controls/imageControl.php ../../../controls/listControl.php ../../../controls/multiDateControl.php ../../../controls/multiListControl.php ../../../controls/multiTextControl.php ../../../controls/textControl.php ../../../includes/dublinFunctions.php ../../../includes/fixity.php ../../../includes/footer.php ../../../includes/header.php ../../../includes/ingestionClass.php ../../../includes/koraSearch.php ../../../includes/menu.php ../../../includes/presetFunctions.php ../../../includes/projectFunctions.php ../../../includes/required.php ../../../includes/schemeFunctions.php ../../../includes/searchTokenFunctions.php ../../../includes/userFunctions.php ../../../includes/utilities.php ../../../accountSettings.php ../../../activate.php ../../../addCollection.php ../../../addControl.php ../../../addScheme.php ../../../assocSearch.php ../../../crossProjectSearch.php ../../../crossProjectSearchResults.php ../../../deleteObject.php ../../../editCollection.php ../../../editControl.php ../../../editOptions.php ../../../editScheme.php ../../../importMultipleRecords.php ../../../importScheme.php ../../../index.php ../../../ingestApprovedData.php ../../../ingestObject.php ../../../login.php ../../../manageAssocPerms.php ../../../manageControlPresets.php ../../../manageDublinCore.php ../../../manageGroups.php ../../../manageProjects.php ../../../manageProjectUsers.php ../../../manageRecordPresets.php ../../../manageSearchTokens.php ../../../manageUsers.php ../../../publicIngest.php ../../../recoverPassword.php ../../../register.php ../../../resetPassword.php ../../../reviewPublicIngestions.php ../../../schemeExport.php ../../../schemeExportLanding.php ../../../schemeLayout.php ../../../searchProject.php ../../../searchProjectResults.php ../../../selectProject.php ../../../selectScheme.php ../../../systemManagement.php ../../../updateDublinCore.php ../../../upgradeDatabase.php ../../../uploadXML.php ../../../viewObject.php
 
 */




// Initial Version: Cassia Miller, 2009
include_once('conf.php');

// list for use in the drop down menus for choosing the language
$locale_list = array('en_US' => 'English',
					 'de_DE' => 'German',
					 'fr_FR' => 'Fran&#231ais');

$language='en_US';

if(isset($_POST['language'])) $_SESSION['language'] = $_POST['language'];
if(isset($_SESSION['language'])) $language=$_SESSION['language'];

// I18N support information here
putenv("LANGUAGE=$language"); 
setlocale(LC_ALL, $language . '.utf8');
// Set the text domain as 'messages'
$domain = 'messages';
bindtextdomain($domain, basePath."locale/"); 
textdomain($domain);
?>