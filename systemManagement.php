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

// Initial Version: Matt Geimer, 2008

require_once('includes/utilities.php');
requireSystemAdmin();

$msg = '';

if (isset($_POST['sysMgt']))
{
	if ($_POST['sysMgt'] == gettext('Update Control List'))
	{
		// get the list of control files
	    $dir = CONTROL_DIR;
	    $controlList = array();
	    if(is_dir($dir)) {
	        if($dh = opendir($dir)) {
	            while(($file = readdir($dh)) !== false) {
	                if(filetype($dir.$file) == "file") {
	                    $controlfile = explode(".",$file);
	                    if(!in_array($controlfile[0], array('index', 'control', 'controlVisitor'))) {
	                        $controlList[] = $controlfile[0];
                            require_once($dir.$file);	                        
	                    }
	                }
	            }
	        }
	    }
	    
	    $dbControls = array();
	    
	    foreach($controlList as $control) {
            $controlName = ucfirst($control);
            $controlInstance = new $controlName();
            $dbControls[] = array('name' => $controlInstance->getType(), 'file' => $control.'.php', 'class' => $controlName, 'xmlPacked' => $controlInstance->isXMLPacked() ? '1' : '0');
        }
        
        // clear the controls list
        $db->query('DELETE FROM control');
        // insert the controls into the table
        foreach($dbControls as $c) $db->query('INSERT INTO control (name, file, class, xmlPacked) VALUES ('.escape($c['name']).', '.escape($c['file']).', '.escape($c['class']).', '.escape($c['xmlPacked']).')');
		
		$msg = gettext('Control List Updated');
	}
	else if ($_POST['sysMgt'] == gettext('Update Style List'))
	{
        // Make sure any rows currently in the DB still exist
        $styleQuery = $db->query('SELECT styleid, filepath FROM style');
        while ($s = $styleQuery->fetch_assoc())
        {
        	if (!file_exists(basePath.'css/'.$s['filepath']))
        	{
        		// Remove any references that projects had to that styleid
        		$db->query('UPDATE project SET styleid=0 WHERE styleid='.$s['styleid']);
        		// Delete the row
        		$db->query('DELETE FROM style WHERE styleid='.$s['styleid'].' LIMIT 1');
        	}
        }

        // Scan for any new XML files
        if ($dirHandle = opendir(basePath.'css'))
        {
        	// Read all the file names
        	while (($filename = readdir($dirHandle)) !== FALSE)
        	{
        		// See if it's a .XML file
        		if (strlen($filename) && substr($filename, -4) == '.xml')
        		{
        			$xml = simplexml_load_file(basePath.'css/'.$filename);
        			// Make sure the necessary components are in place and the
        			// file exists
        			if (isset($xml->file) && isset($xml->name) && file_exists(basePath.'css/'.(string)$xml->file))
        			{
                        // Make sure no other record for this file exists, then insert a
                        // record
                        $testQuery = $db->query('SELECT styleid FROM style WHERE filepath='.escape((string)$xml->file).' LIMIT 1');
                        if ($testQuery->num_rows == 0)
                        {
                        	$db->query('INSERT INTO style (description, filepath) VALUES ('.escape((string)$xml->name).','.escape((string)$xml->file).')');
                        }
        			}
        		}
        	}
        }
	}
}

include_once('includes/header.php');

echo '<h2>'.gettext('System management').'</h2>';
if(!empty($msg)) echo '<div class="error">'.gettext($msg).'</div><br />'; ?>
<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
<input type="submit" name="sysMgt" value="<?php echo gettext('Update Control List');?>" /><br /><br />
<input type="submit" name="sysMgt" value="<?php echo gettext('Update Style List');?>" />
</form>
<br />
<form action="upgradeDatabase.php" method="post">
<input type="submit" value="<?php echo gettext('Upgrade Database Layout');?>" /><br />
</form>
<?php 
include_once('includes/footer.php');
?>
