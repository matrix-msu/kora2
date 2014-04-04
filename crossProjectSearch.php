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

include_once('includes/utilities.php');
requireLogin();

include_once('includes/header.php');

echo '<h2>'.gettext('Cross-Project Search').'</h2>';
?>

<form method="post" action="crossProjectSearchResults.php">

<table class="table">
    <tr>
        <td>
            <b><?php echo gettext('Keywords');?></b>
        </td>
        <td>
            <input type="text" name="keywords" id="keywords" size="70" />
        </td>
    </tr>
    <tr>
        <td>
            <b><?php echo gettext('Boolean');?></b>
        </td>
        <td>
            <select name="boolean" id="boolean">
                <option value="AND" selected="selected"><?php echo gettext('AND');?></option>
                <option value="OR"><?php echo gettext('OR');?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td>
            <b><?php echo gettext('Projects');?></b>
        </td>
        <td>
            <select name="projects[]" multiple="multiple" size="5">
<?php 
// Get the list of projects the user is allowed to search
if (isSystemAdmin())
{
    $projectQuery = 'SELECT pid, name FROM project WHERE active=1 ORDER BY name';
} else {
	$projectQuery  = 'SELECT project.pid AS pid, project.name AS name FROM member LEFT JOIN project USING (pid)';
	$projectQuery .= ' WHERE member.uid='.$_SESSION['uid'].' AND project.active=1 ORDER BY project.name';
}

$projectQuery = $db->query($projectQuery);
while($project = $projectQuery->fetch_assoc())
{
    echo '                <option value="'.$project['pid'].'" selected="selected">'.htmlEscape($project['name'])."</option>\n";	
}
?>
            </select>
        </td>
    <tr>
        <td colspan="2"><input type="submit" value="<?php echo gettext('Search');?>" /></td>
    </tr>
</table>

</form>

<?php 
include_once('includes/footer.php');

?>
