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
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * searchProject
 * 
 * GET/POST arguments:
 *   pid: project id                (REQUIRED)
 *   sid: scheme  id                (REQUIRED)
 * 
 * if keyword not specified, pulls all objects from project/scheme
 * if scheme not specified, searches entire project  
 */

// Initial Version: Meghan McNeil, 2009

require_once('includes/utilities.php');
include_once('includes/ingestionClass.php');
requireLogin();

// a project and scheme ID must be passed; if it is not, 
// something is wrong so fall back to the project index page
if (!isset($_REQUEST['pid']) || !isset($_REQUEST['sid']) || empty($_SESSION['currentScheme'])) {
	header('Location: projectIndex.php');
}
include_once("includes/xmlDataHandler.php");
include_once("includes/controlDataFunctions.php");
include_once('includes/header.php');

function showForm() {
	// remove any previous control mappings.
	unset($_SESSION['controlMapping_'.$_REQUEST['sid']]);
	?>
		<form enctype="multipart/form-data" name="xmlUploadForm" id="xmlUploadForm" action="" method="post">
			<p>
		    <label for="xmlFileName"><?php echo gettext('XML File to Load: ');?></label><input id="xmlFileName" name="xmlFileName" type="file" /><br/>
		    <label for="zipFolder"><?php echo gettext('Zip Folder: ');?></label><input id='zipFolder' name="zipFolder" type="file" />
		    </p>
		    <p><input type="submit" name="submit" value="<?php echo gettext('Upload Records');?>"/></p>
		</form>
	<?php 
}

?>

<script src="javascripts/MappingManager.js" type="text/javascript"></script>
<script type="text/javascript">
function cancelIngestion() {
	window.location = "importMultipleRecords.php?pid=<?php echo $_REQUEST['pid']?>&sid=<?php echo $_REQUEST['sid']?>";
}
</script>




<h2><?php echo gettext('Upload an XML file');?></h2>  

<?php 
// display results instead of trying to import again 
if(@$_GET['page']=='results' && !empty($_SESSION['ob'])){
	echo $_SESSION['ob'];
	include_once('includes/footer.php');
	die;
}


print '<div id="xmlActionDisplay">';

if (isset($_POST['submit'])) {
	if (isset($_FILES['xmlFileName']) && $_FILES['xmlFileName']['type'] == 'text/xml') {
		
		//unset session variables that will be used
		if (isset($_SESSION['xmlRecordData'])) {
			unset($_SESSION['xmlRecordData']);
		}
		
		$uploadedFiles = false;
		$zipFiles = true;
		//if a zip folder was uploaded (ie error field != 4), extract files
		if (isset($_FILES['zipFolder']) && $_FILES['zipFolder']['error'] != 4) {
			$uploadedFiles = true;
			$zipFiles = extractZipFolder($_FILES['zipFolder']['tmp_name'],$_FILES['zipFolder']['name']);
			if(!$zipFiles){
				// error already printed in function
				showForm();
				print '</div>';
				include_once('includes/footer.php');
				die;
			}
		}
		
		//load record and build mapping table
		$xmlObject = simplexml_load_file($_FILES['xmlFileName']['tmp_name']);
		if ($xmlObject) {
			//create xml data handler
			$xmlDataSet = new XMLDataHandler($_REQUEST['pid'],$_REQUEST['sid'],$uploadedFiles);
			
			//Record data is contained within the Data tag only - other parts of the file
			//are used for scheme import
			//If statement is for backwards compatibility - previously Data was the document root
			if($xmlObject->getName() == 'Scheme')$xmlObject = $xmlObject->Data;
			
			//load data from XML
			if ($xmlObject->ConsistentData) {
				$xmlDataSet->loadConsistentData($xmlObject->ConsistentData);
			}

			$recordArray = array();
			for ($i=0; $i<count($xmlObject->Record); $i++) {
				$xmlDataSet->loadSpecificData($xmlObject->Record[$i]);
				$recordArray[] = $xmlDataSet->getRecordData();
			}		

			//save record data
			$_SESSION['xmlRecordData'][$_SESSION['currentScheme']] = $recordArray;
			
			//draw control mapping table
			echo '<div id="mainTable">';
			$xmlDataSet->drawControlMappingTable();
			echo '</div>';
			
			echo '<div id="additionalMapping">';
			if (!empty($xmlDataSet->associationArray)) {
				for($i=0; $i<count($xmlDataSet->associationArray); $i++) {
					addNewMappingTable($xmlDataSet->associationArray[$i][0],$xmlDataSet->associationArray[$i][1],$xmlDataSet->associationArray[$i][2],$xmlDataSet->associationArray[$i][3]);
				}
			}
			echo '</div>';
			
//			return $recordArray;
			
		} else {
			print '<div class="error">'.gettext('**ERROR: Could not open xml file').'</div>';		
		}
		
	} else { 
		print '<div class="error">'.gettext('**ERROR: Please upload an xml file').'</div>';
		showForm(); 
	}
} else {
	unset($_SESSION['ob']);
	showForm();
}

print '</div>';
include_once('includes/footer.php');
?>
