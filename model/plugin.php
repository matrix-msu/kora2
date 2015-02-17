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
along with this program.  If not, see <http://www.gnu.org/licenses/>. */

// Initial Version: Nicole Lawrence, 2014

require_once(__DIR__.'/../includes/includes.php');

/**
 * @class Plugin object
 */
 class Plugin
 {
	const CSS_CORE = 0;
	const CSS_LIB = 100;
	const CSS_CLASS = 1000;
	const CSS_THEME = 10000;
	const CSS_END = 65536;
	
	const JS_CORE = 0;
	const JS_LIB = 100;
	const JS_CLASS = 1000;
	const JS_END = 65536;
	
	private static $db = null;
	//private static $js = [];
	//private static $css = [];
	public static $plugins = [];
	
	public static function Init()
	{
		Plugin::DetectNewPlugins();
	}
	
	/// figure out what this function is doing
	public static function SelectAllPlugins()
	{
		global $db;
		$this_table="koraPlugins";
		$query = "SHOW tables like '$this_table'";
		$res = $db->query($query);
		
		if(!empty($res)){
            $query = "SELECT * FROM koraPlugins";
            $result = mysqli_query($db, $query);
        
            $allPluginsArray = array();
        
            while($row = mysqli_fetch_array($result))
            {
                $allPluginsArray[] = $row;
            }
		
            return $allPluginsArray;
		}
	}
	
	public static function PluginsExist() { return !empty(Plugin::$plugins); }
	
	/// Looks for new plugins. If one is found, inserts the necessary information into the database
	public static function DetectNewPlugins()
	{
		global $db;
		/// Loop through plugin folders
		// Gets all files in the plugin directory
		$pluginsDirectories = glob("plugins/*", GLOB_ONLYDIR);
		$readConfig = false;
		
		Plugin::ScanForPlugins();
		
		for($i = 0; $i < count($pluginsDirectories); $i++) // Iterate through plugins
		{
			Plugin::$plugins[substr($pluginsDirectories[$i], 8)]['FileName'] = $pluginsDirectories[$i];
			if ($dirHandle = opendir(basePath.$pluginsDirectories[$i]))
			{
				/// Check to see if plugin exists
				$pluginName = substr($pluginsDirectories[$i], 8);
				$pluginFileName = $pluginName;

				/// Make the plugin name look presentable
				for($j = 0; $j < strlen($pluginFileName)-1; $j++)
				{
					if($pluginFileName[$j] == '_')
					{
						$pluginFileName[$j] = ' ';
					}
				}

				$pluginFileName = ucwords($pluginFileName);
				
				$query = "SELECT pluginName FROM koraPlugins WHERE pluginName = '$pluginName'";
				$result = mysqli_query($db, $query);
				
				/// If false, collect data and insert new plugin
				if(mysqli_num_rows($result) == 0)
				{
					
					//Open conf file
					$fileHandle = fopen(basePath ."plugins/".$pluginName . "/config", "r");
					$menuPath = "";
					if ($fileHandle)
					{
						while(!feof($fileHandle))
						{
							$line = fgets($fileHandle);
							$line = rtrim($line, "\r\n");
							switch($line) // Search for config tags
							{
								case "[Menus]":
									$currentProp = "Menu";
									break;
								
								default:
									//Get Menu titles and locations and put them in an array
									$menuPath .= $line .",";
									break;
							};
						}
						$menuPath = substr($menuPath, 0, strlen($menuPath)-1);
					}
					fclose($fileHandle);
					
		
					$jsFileName = "";
					$cssFileName = "";
					//$phpFileName = "";

					/// Gather all the js filenames
					foreach(glob("$pluginsDirectories[$i]/js/*.js") as $fileName)
					{
						$jsFileName = $jsFileName . substr($fileName, strlen($pluginsDirectories[$i])+4) . ",";
					}
					$jsFileName = substr($jsFileName, 0, strlen($jsFileName)-1);
					
					/// Gather all the css filenames
					foreach(glob("$pluginsDirectories[$i]/css/*.css") as $fileName)
					{
						$cssFileName = $cssFileName . substr($fileName, strlen($pluginsDirectories[$i])+5) . ",";
					}
					$cssFileName = substr($cssFileName, 0, strlen($cssFileName)-1);
					
					/// Gather all the menu_*.php filenames
					/*foreach(glob("$pluginsDirectories[$i]/menu_*.php") as $fileName)
					{
						$phpFileName = $phpFileName . substr($fileName, strlen($pluginsDirectories[$i])+1) . ",";
					}
					$phpFileName = substr($phpFileName, 0, strlen($phpFileName)-1);*/
					
					Plugin::InsertNewPlugin($pluginName, $jsFileName, $cssFileName, $menuPath, $pluginFileName);
				}
				closedir($dirHandle);
			}
		}
	}
	
	/// Inserting the necessary information into the database for every new plugin
	public static function InsertNewPlugin($pluginName, $jsFileName, $cssFileName, $phpFileName, $pluginFileName)
	{
		global $db;
		
		$minKORAVer = KORA_VERSION;
		$minDBVer = LATEST_DB_VERSION;
		//Insert data into the database NOTE: need to figure out what feilds we need
		$queryString = "INSERT INTO koraPlugins 
						    (pluginName, minKORAVer, minDBVer, javascriptFiles, cssFiles, menus, enabled, fileName, description)
							VALUES('$pluginFileName', '$minKORAVer', '$minDBVer', '$jsFileName', '$cssFileName', '$phpFileName', '0', '$pluginName', '')";
		$db->query($queryString);
	}
	
	///Need to grab which plugin we are trying to enable, insert to the table and remove from size list
	public static function EnablePlugin($pluginName)
	{
		global $db;
		$query = "UPDATE koraPlugins SET enabled='1' WHERE pluginName='$pluginName'";
		
		$db->query($query);
		
		
	}
	
	///Need to grab which plugin we are trying to disable, insert to the table and remove from size list
	public static function DisablePlugin($pluginName)
	{
		global $db;
		$query = "UPDATE koraPlugins SET enabled='0' WHERE pluginName='$pluginName'";
		
		$db->query($query);
	}
	
	/**
	  * Checks for an install file and turns true or false
	  *
	  * @return void
	  */
	public static function IsInstall($pluginName)
	{
		//Look in file system
		if ($dirHandle = opendir(basePath."plugins/".$pluginName."/"))
		{
			$baseLength = strlen(basePath);
			$pluginLength = strlen($pluginName);
			foreach(glob(basePath."plugins/"."$pluginName/*.php") as $fileName)
			{
				$fileName = substr($fileName, $baseLength+8+$pluginLength+1);
				
				//If intall.php exists, return true
				if($fileName == "install.php")
				{
					closedir($dirHandle);
					return true;
				}
			}
		}
		
		//If it does not exist, return false
		closedir($dirHandle);
		return false;
	}
	
	/**
	  * Capatures all plugins in an array
	  *
	  * @return void
	  */
	public static function ScanForPlugins()
	{
		global $db;
		$this_table="koraPlugins";
		$query = "SHOW tables like '$this_table'";
		$res = $db->query($query);
		if(!empty($res)){
    		$queryString = "SELECT * FROM koraPlugins";
	    	$results = $db->query($queryString);
		    while($row = mysqli_fetch_row($results)){
			    Plugin::$plugins[$row[0]]['MinKORAVer'] = $row[1];
    			Plugin::$plugins[$row[0]]['MinDataVer'] = $row[2];
	    		Plugin::$plugins[$row[0]]['Javascript'] = $row[3];
		    	Plugin::$plugins[$row[0]]['CSS'] = $row[4];
			    Plugin::$plugins[$row[0]]['Menu'] = $row[5];
			    Plugin::$plugins[$row[0]]['Enabled'] = $row[6];
			    Plugin::$plugins[$row[0]]['FileName'] = $row[7];
			    Plugin::$plugins[$row[0]]['Description'] = $row[8];
			    Plugin::$plugins[$row[0]]['pluginName'] = $row[0];
		    }
		}
	}
	
	
	/**
	  * Gets all the menu and menu urls from the conf file
	  *
	  * @return Menu_menus array of [location]=>menu title
	  */
	public static function GetMenus($pluginName)
	{
		//Open conf file
		$fileHandle = fopen(basePath ."plugins/".$pluginName . "/config", "r");
		if ($fileHandle)
		{
			while(!feof($fileHandle))
			{
				$line = fgets($fileHandle);
				$line = rtrim($line, "\r\n");
				switch($line) // Search for config tags
				{
					case "[Menus]":
						$currentProp = "Menu";
						break;
					
					default:
						//Get Menu titles and locations and put them in an array
						$keyVal = explode(" = ", $line);
						$menuName["plugins/$pluginName/".$keyVal[1]] = $keyVal[0];
						break;
				};
			}
		}
		fclose($fileHandle);
		
		return $menuName;
	}
	
	
	/**
	  * Prints html form for editing a plugin
	  *
	  * @return void
	  */
	public static function PrintEditPlugin($pluginName)
	{
		$listOfAllPlugins = Plugin::SelectAllPlugins();
		?>
		<h2><?php echo gettext('Edit Plugin ');?><?php echo htmlEscape($listOfAllPlugins[$pluginName][0])?></h2>
		<div id='cbox_error'></div>
		<div id="project_editPlugin_form">
		<input type="hidden" name="pluginSubmit" value="true" />
		<table class="table_noborder">
		<tr><td align="right"><?php echo gettext('Name');?>:</td><td><input type="text" class="project_editPlugin_name" <?php echo ' value="'.$listOfAllPlugins[$pluginName][0].'" ';?> /></td></tr>
		<tr><td align="right"><?php echo gettext('Description');?>:</td><td><textarea class="project_editPlugin_desc"><?php echo $listOfAllPlugins[$pluginName][8];?></textarea></td></tr>
		<tr><td colspan="2" align="right"><input type="button" class="project_editPlugin_submit" value="<?php echo gettext('Edit Scheme');?>" /></td></tr>
		</table>		
		</div>    
		<?php 
	}
 }
?>