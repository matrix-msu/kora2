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

// Initial Version: Brian Beck, 2008

require_once('includes/utilities.php');

requireProject();
	
// show the search form

include_once('includes/header.php');

echo '<h2>'.gettext('Search').': '.$_SESSION['currentProjectName'].'</h2>';?>
<br />
<form action="searchProjectResults.php" method="get">
<input type="hidden" name="pid" value="<?php echo $_SESSION['currentProject']?>" />
<table>
    <tr>
        <td><?php echo gettext('Scheme');?>:</td>
        <td><select name="sid">
            <option value=""><?php echo gettext('All');?></option>
<?php 
            // show options for all schemes in the project
            $schemeList = $db->query('SELECT schemeid, schemeName FROM scheme WHERE pid='.$_SESSION['currentProject']);
            while ($scheme = $schemeList->fetch_assoc()) {
            	echo '<option value="'.$scheme['schemeid'].'">'.htmlEscape($scheme['schemeName']).'</option>';
            }
?>
        </select></td>
    </tr>
    <tr>
        <td><?php echo gettext('Keywords');?>:</td>
        <td><input type="text" name="keywords" /></td>
    </tr>
    <tr>
        <td></td>
        <td>
            <input type="radio" name="boolean" value="OR" checked="checked" /> <?php echo gettext('Any');?>
            <input type="radio" name="boolean" value="AND" /> <?php echo gettext('All');?>
        </td>
    </tr>
    <tr>
        <td></td>
        <td><input type="submit" value="<?php echo gettext('Search');?>" /></td>
    </tr>
</table>
</form>
<?php 
include_once('includes/footer.php');

?>
