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

//Initial version Rob Allie, 2010

require_once('includes/utilities.php');

requireLogin();
//Check that user is an admin for the current project (or a site admin)
requireProjectAdmin();

if(!isset($_GET['download']))
{
	
	$pid = $_SESSION['currentProject'];
	$sid = $_SESSION['currentScheme'];
	$dataTable = "p".$pid."Data";
	$controlTable = "p".$pid."Control";
	
	$xmlPacked = array();
	//need to include control files for xml-packed types
	$controlQuery = "SELECT class,file FROM control WHERE xmlPacked = 1";
	$controls = $db->query($controlQuery);
	if(is_object($controls) && $controls->num_rows > 0){
		while($con = $controls->fetch_assoc()){
			include_once("controls/".$con['file']);
			$xmlPacked[] = $con['class'];
		}
	}
	
	//Get all control data for scheme structure export and dictionary
	$dictQuery = "SELECT * ";
	$dictQuery .= "FROM $controlTable ";
	$dictQuery .= "WHERE $controlTable.schemeid = $sid";
	$dictQuery = $db->query($dictQuery);
	
	//If no control results, the scheme has no controls - stop processing
	if(!is_object($dictQuery) || $dictQuery->num_rows < 1){
		include_once('includes/header.php');
		echo gettext("No controls for scheme ").$_SESSION['currentSchemeName'].gettext(" of project ").$_SESSION['currentProjectName'];
		include_once('includes/footer.php');
		die();
	}
	
	//If the result set is not empty, build up dictionary and reverse dictionary for the scheme
	$dictionary = array();
	$reverseDictionary = array();
	while($dictRow = $dictQuery->fetch_assoc()){
		$dictionary[$dictRow['name']] = $dictRow;
		$reverseDictionary[$dictRow['cid']] = $dictRow['name'];
	}
	//Get all data for the scheme
	$dataQuery = "SELECT id, cid, value FROM $dataTable ";
	$dataQuery .= "WHERE schemeID = $sid ORDER BY id, cid";
	$datas = $db->query($dataQuery);

	//If the result set is empty, the scheme has no data
	$noData = false;
	if(!is_object($datas) || $datas->num_rows < 1)
	{
		$noData = true;
	}
	
	$z = 0;//initialize index for # of zip files (files can only be 4gb)
	$zipSize = 0;//initialize size of current zip being prepared
	$sizeArray = array();//to keep track of each zip files size
	$zipArray = array();//array to hold the arrays of zip paths
	$zipFiles = array();//array to hold the zip paths
	$id = '';
	while($data = $datas->fetch_assoc())
	{
		if($data['cid'] != "0")
		{
			$cName = $reverseDictionary[$data['cid']];
		}
		else
		{
			continue; //This is the reverse associator, which we can't use
		}
		if(in_array($dictionary[$cName]['type'],$xmlPacked))
		{
			$thisCon = new $dictionary[$cName]['type'];
			$values = $thisCon->storedValueToSearchResult($data['value']);
			if($dictionary[$cName]['type'] == 'FileControl' || $dictionary[$cName]['type'] == 'ImageControl')
			{
				//***build the array that hold the pathnames**
				//   used for adding files to zip
				$fileParts = explode('-', $values['localName']);
	    		$zpid = hexdec($fileParts[0]);
	    		$zsid = hexdec($fileParts[1]);
	    		$filePath = fileDir.$zpid.'/'.$zsid.'/'.$values['localName'];
	    		$fileName = $values['localName'];
				$fileArray = array("path"=>$filePath, "name"=>$fileName);
				//get file size
				$fileSize = filesize($filePath);
				$zipSize += $fileSize;
				if($zipSize < 4294967296)//check if zip will be less than 4gb
				{
					if(!array_key_exists($z, $zipArray))
					{
						$zipArray[$z] = array();
					}
					if(file_exists($filePath))
					{
						$zipArray[$z][] = $fileArray;
					}
				}
				else//zip size is greater than 4gb and we need a new array (new zip)
				{
					$sizeArray[$z] = $zipSize - $fileSize;
					$z++;
					$zipArray[$z] = array();
					$zipArray[$z][] = $fileArray;//we also need to add the current filepath
					$zipSize = $fileSize;//zip size is now current file size
				}
				$sizeArray[$z] = $zipSize;
			}
		}
	}

}

//for zip creation
if(isset($_GET['zip']))
{
	$zipIndex = $_GET['zip'];
	$zipNum = $zipIndex+1;
	$dest = tempnam(sys_get_temp_dir(),'foo');
	
	$name = $_SESSION['currentProjectName'].'-'.$_SESSION['currentSchemeName'].'-files_';
	$name = str_replace(" ", "_", $name);
	$name = htmlentities($name);
            
	
	$zip = new ZipArchive();
	$zip->open($dest, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
	foreach($zipArray[$zipIndex] as $file)
	{
		$zip->addFile($file['path'],$name.$zipNum."/".$file['name']);
	}
	$zip->close();


	//send headers and the file directly
	header("Content-Type: application/zip");
	header("Content-Disposition: attachment;filename=$name$zipNum.zip");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: ".filesize($dest));
	
	readfile($dest);
	
	//Delete the temporary file ... don't clutter the server
	unlink($dest);
	
	exit();
}

if(!isset($_GET['zip']) && !isset($_GET['download']))
{
	//echo "<pre>";print_r($zipArray);echo "</pre>";//zip data
	include_once('includes/header.php');


	//javascript includes?>
	<?php

	echo "<h4>Download XML</h4>";
	if($noData){
	    echo '<a href="schemeExport.php?type=scheme">'.gettext('Download the Scheme xml.').'</a><br /><br />';
		echo gettext("No data for scheme ").'"'.$_SESSION['currentSchemeName'].'"'.gettext(" of project ").'"'.$_SESSION['currentProjectName'].'".';
	}else{
		echo '<a href="schemeExport.php?type=data&scheme='.$sid.'">'.gettext('Download the Record Data xml.').'</a><br /><br />';
	    echo '<a href="schemeExport.php?type=scheme">'.gettext('Download the Scheme xml.').'</a><br /><br />';
		echo '<a href="schemeExport.php">'.gettext('Download the Record Data xml and Scheme xml in one file.').'</a><br /><br />';
	}
	
	if($zipSize>0){
		echo "<h4>Download Files</h4>";
        echo gettext("This export requires files located in ").($z+1).gettext(" zip file(s).")."<br /><br />";
        echo gettext("Click on the apporopriate link to create and download each file.")."<br />";
        echo gettext("These files may take a while to create and download depending on the size.")."<br /><br />";

        for($i=0;$i<sizeof($sizeArray);$i++)
        {
            $MB = $sizeArray[$i]/1000000;
            $MB = round($MB, 2);
            
            $name = $_SESSION['currentProjectName'].'-'.$_SESSION['currentSchemeName'].'-files_'.($i+1).".zip";
            $name = str_replace(" ", "_", $name);
            $name = htmlentities($name);
            

			echo "<a href=\"#$i\" id=\"zipfile$i\">$name</a>  $MB MB <span id=\"dlmsg\"></span><br />";
		}
?>
<br />
<?php
	}
}


include_once('includes/footer.php');
?>

<script type="text/javascript">


/*
 *
 *
 */
<?php
for($j=0;$j<=$z;$j++)
{?>
	$(document).ready(function() {
		$('#zipfile<?php echo $j; ?>').click( function() {
			var data = 'zip=<?php echo $j; ?>';

			$("#dlmsg").html('<strong><?php echo gettext('Generating Zip File, Please Wait...');?></strong>');
			window.location = "schemeExportLanding.php?zip=<?php echo $j; ?>";
			
			return false;
		});
	});
<?php }
?>

/*
 *
 *
 */

 
</script>
