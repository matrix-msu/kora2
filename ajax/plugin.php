<?php
require_once(__DIR__.'/../includes/includes.php');
require_once('../model/plugin.php');

Manager::Init();

$action = $_REQUEST['action'];
$id = $_REQUEST['id'];

// If we are activating or deactivating a plugin
if($action == 'activation'){

	//Get a list of all the plugins
	$listOfPlugins = Plugin::SelectAllPlugins();

	//Get the plugin name of the plugin we want to activate/deactivate
	$pluginName = $listOfPlugins[$id]['pluginName'];
	
	//Actually doing the activate or deactivate
	if ($listOfPlugins[$id]['enabled'] == 0){
		//See if the plugin has an install.php file, if so run that file.
		$isInstall = Plugin::IsInstall($listOfPlugins[$id]['fileName']);

		if($isInstall){
			require_once basePath.'plugins/'.$listOfPlugins[$id]['fileName'].'/install.php';
		}

		Plugin::EnablePlugin($pluginName);
	}
	else if ($listOfPlugins[$id]['enabled'] == 1){
		Plugin::DisablePlugin($pluginName);
	}
}
else if ($action == 'Editplugin'){	
	//Get a list of all the plugins
	$listOfPlugins = Plugin::SelectAllPlugins();
	
	//Get the id and then the plugin name of the plugin we want to edit
	$pluginName = $listOfPlugins[$id]['pluginName'];
	
	Plugin::PrintEditPlugin($pluginName, $id);
}

else if ($action == 'submitDescription'){
	$description = $_REQUEST['description'];
	
	//Get a list of all the plugins
	$listOfPlugins = Plugin::SelectAllPlugins();
	
	//Get the id and then the plugin name of the plugin we want to edit
	$pluginName = $listOfPlugins[$id]['pluginName'];

	Plugin::UpdateDescription($pluginName, $description);
}
?>