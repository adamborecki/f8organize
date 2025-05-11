<?php


// written by Adam Borecki

 /** CHANGE LOG
  * 
  * Aug 6, 2018 - development
  */

// about this script
$script_info = array(
    "name" => "f8organize",
    "bash_name" => "f8organize",
    "author" => "Adam Borecki",
    "organization" => "recording.LA",
    "download_url" => "http://recording.LA/software",
    "minimum_version_url" => "http://downloadaccess.adamborecki.com/autocopy/v0.x/minimum_version.php",
    "description" => "This script copies files from multiple SD cards (volumes) to HDDs or SSDs (volumes).",
    "version" => array(
        "major" => "0",
        "minor" => "1",
        "date" => "August 9, 2018",
    ),
    "history_path" => "/Users"."/".exec("whoami")."bin/Autocopy/history/",
    "todo" => array(
        "allow destionation_path to be changed by user. will required some formatting and validation stuff. do that later not important enough now",

        "validate last transfer",
        "demo filesizes",
        "post reports to recording.LA database",
    ),
);
echo "--- $script_info[name] by $script_info[author], $script_info[organization]\n";
echo "--- version {$script_info['version']['major']}.{$script_info['version']['minor']} ({$script_info['version']['date']})\n";
echo "\n";




echo "\t** $script_info[name] by Adam Borecki -- recording.LA\n\n";

echo "\n\nVERSION CHECK\n";
echo "You're running version ".$script_info['version']['major'].".".$script_info['version']['minor']." - ".$script_info['version']['date']."\n";
$output = readline("Please type in OK (exactly) if you want to continue\n");
if($output != "OK")
	die("You must type OK if this version is acceptable to use.\n\n");


$script_starttime = time();

$debug = false;

// usable mas self, with forced trailing slash
$scriptsdir = pathinfo($_SERVER['PHP_SELF'],PATHINFO_DIRNAME)."/";


 // load the config $CONFIG
$config_dir = $scriptsdir."../$script_info[bash_name]-config/";
$installation_names = array("recordingLA","borecki","chapman");
$valid_config_files = array();
foreach($installation_names as $installation_name){
	if(file_exists($config_dir."config.$installation_name.php")){
		$valid_config_files[$installation_name] = $config_dir."config.$installation_name.php";
	}
}
switch(count($valid_config_files)){
	case 0:
		die("No valid config files were found in $config_dir - please contact Adam for help or download a config file.\n");
	case 1:
		// even though there's only 1. this is lazy enough, adam! :P
		foreach($valid_config_files as $k => $v){
			require_once $v;
		}
		break;
	default:
		echo "Multiple configuration files are available. Please type one of the following options:\n";
		foreach($valid_config_files as $k => $v){
			echo "\t$k\n";
		}
		$user_input = readline("Which config would you like to load: ");
		while(!isset($valid_config_files[$user_input]) && $user_input!="quit"){
			echo "Sorry, that's not a valid choice. Please enter one of the options above, or type 'quit' to exit.\n";
			$user_input = readline("Which config would you like to load: ");
		}
		if($user_input == "quit")
			die("Exiting.\n\n");
		else{
			require_once $valid_config_files[$user_input];
		}
}
if(!isset($CONFIG)){
	die("Missing or improperly configured \$CONFIG! Please upload a valid config file to $config_dir\n");
}





echo "\t** Config loaded for: $CONFIG[name] **\n\n";


date_default_timezone_set('America/Los_Angeles');
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit','4000M'); // maximum file size, should be as big as possible because it has to fread it binary :(
// it probably caps out at 1GB though, that looks like maximum :(


/**
 * 
 *  OVERALL PLAN:
 * _______________
 * 
 * make sure it matches richard's regex with optional trailing slash
 * 
 * force it to have a trailing slash
 * 
 * run the preg function (input, and folder) 
 * 
 * safely create a new folder that will be created by file move
 * 				mkdir($pathname, $mode=0777, $recursive=TRUE)
 * 				rename()
 * 
 * 
 */

echo getcwd();
echo getcwd();
echo "\n\n\n";

$max_results = 3*8*300 + 20; // maximum 3 devices * 8 channels * 300 takes + 20 spares
// this is used in several functions

$file_paths = array(
	// relative paths w filename
);
 
// working directory
$wd = getcwd();
if(substr($wd,-1)!=DIRECTORY_SEPARATOR)
	$wd .= DIRECTORY_SEPARATOR;

// make sure they cd'd the directory
$bash_user_name = exec("whoami");
if( $wd == "/Users/$bash_user_name" || $wd == "/Users/$bash_user_name/")
	throw new Exception("Please use `cd` to go to the folder you want to use first. (don't click and drag it into the command line). Right now, the working directory (\$wd) is the default, /Users/$bash_user_name which is assumed to be incorrect.");

$glob_output = null;
$glob_command = "*";
$max_glob_directories = 7;
for($i = 0;$i <= $max_glob_directories && $glob_output = glob($glob_command); $i++) {
	$new_files = $glob_output;
	foreach($new_files as $key => $new_file){
		if(is_dir($wd . $new_file)){
			unset($new_files[$key]);
		}
	}
	$file_paths = array_merge($file_paths,$new_files);
	$glob_command .= "/*";
	if(count($file_paths) >= $max_results){
		
		throw new Exception("Please cd to a different directory!!\nError: results of glob(*/*...) would exceed maximum $max_results \$max_results, so you probably have too many files or didn't specify a correct working directory.");
		//trigger_error("Warning: results of glob(*/*...) would exceed maximum $max_results \$max_results, so you probably have too many files or didn't specify a correct working directory.", E_USER_WARNING);
		break;
	}
}


// define fields
$attributes = array(
	array(
		"name"=>"parent_folder",
		"default"=>"no parent folder",
		"required"=>false,
		"run_on_keys"=>array("dir"), // run regex on file[ key1,key2 ]
		"pattern"=>"/(\w+)\/\d{6}\(?\d?\)?\/\d{6}-/",
	),
	array(
		"name"=>"day",
		"default"=>"unknown day",
		"required"=>true,
		"run_on_keys"=>array("dir","name"), // run regex on file[ key1,key2 ]
		"pattern"=>"/(\d{6}\(?\d?\)?)/",
	),
	array(
		"name"=>"take",
		"default"=>"unknown take",
		"required"=>true,
		"run_on_keys"=>array("name","dir"), // run regex on file[ key1,key2 ]
		"pattern"=>"/(T(\d{3}))/",
		"extra_matches"=>array(
			"take_number"=>array("type"=>"string","value"=>"","required"=>false),
		),
	),
	array(
		"name"=>"track",
		"default"=>"unknown track",
		"required"=>true,
		"run_on_keys"=>array("name"), // run regex on file[ key1,key2 ]
		"pattern"=>"/(Tr(\d{1,2}_?\d?)(_?D?))/",
		"extra_matches"=>array(
			"input"=>array("type"=>"string","value"=>0,"required"=>true),
			"is_dual_channel"=>array("type"=>"bool","value"=>false,"required"=>false),
		),
	),
	//*/
);

$kill_triggers = array(
	"/_f8organize/"=>"A file or path included '_f8organize', which probably means that either\n\t(1) f8organize might have already been run, or \n\t(2) you might have cd'd into a directory that wasn't specific enough.\nPlease change cd to a more specific folder.\n\n",
);

// "^\\d{6}\\-T\\d{3,5}_Tr\\w{1,5}\\.WAV\$"

$files = array();

// process each filename
$debug_data_process = false;

$limit_counter = 0;
foreach($file_paths as $file_path_from_glob){
	$file = newFile( $wd . $file_path_from_glob);

	foreach($kill_triggers as $pattern => $error_msg){
		if( preg_match($pattern,$file['path']) ){
			throw new Exception("Error! Script was stopped by a kill_trigger: $error_msg");
		}
	}

	if($debug_data_process) echo "\n\n***** ANNOUNCING: new file is $file[name] in $file[dir]\n";

	foreach($attributes as $attr){
		$is_found = false;
		$file[ $attr['name'] ] = $attr['default'];
		foreach( $attr['run_on_keys'] as $key ){
			if($is_found)
				break;
			$matches = null;
			if($debug_data_process) echo "Let's try ".$attr["pattern"] ." on" . $file[ $key ]."\n";
			preg_match_all($attr["pattern"], $file[ $key ], $matches);
			if( preg_last_error() != PREG_NO_ERROR ){
				throw new Exception("regex failed to run for this attribute, $attr[name]".array_flip(get_defined_constants(true)['pcre'])[preg_last_error()]);
			}
			if(isset($matches[1][0]) && !empty($matches[1][0]) ){
				$file[ $attr['name'] ] = $matches[1][0];
				$is_found = true;
				if($debug_data_process) echo "< FOUND IT: ".$matches[1][0]."\n";
				if(isset($attr['extra_matches'])){
					if($debug_data_process) {
						echo ">>> BONUS JOURNEY! let's look for extra matches....\n";
						echo "\$attr['extra_matches'] = \n";
						print_r($attr['extra_matches']);
						echo "\n\n";
						echo "\$matches = \n";
						print_r($matches);
						echo "\n\n";
					}
					$extra_count = 2; // 1st index (0) of matches is the whole result, index 1 (second result) is the match for the thing, any further matches are starting at index 2 (result 3), which is the second regex subgroup
					foreach($attr['extra_matches'] as $extra_attr_name => $extra_attr_info){
						$file[ $extra_attr_name ] = null;
						$extra_is_found = false;
						$extra_value = null;
						if(isset($matches[$extra_count][0])){
							$extra_value = $matches[$extra_count][0];
							$extra_is_found = true;
						}
						switch($extra_attr_info["type"]){
							case "bool":
								$file[ $extra_attr_name ] = $extra_value ? true : false;
								break;
							case "string":
								$file[ $extra_attr_name ] = $extra_value;
								break;
							default:
								throw new Exception("Unable to handle the extra_match requested for attr $attr[name] for extra attribute $extra_attr_name");
						}
						if(!$extra_is_found && $extra_attr_info['required'])
							throw new Exception("Unable to find required extra_match on attr $attr[name] for required extra attribute $extra_attr_name");
						$extra_count++;
					}
				}
			}
		}
		if($debug_data_process) echo "******* RESULT $attr[name]=".$file[ $attr['name'] ]."\n";
		if(!$is_found && $attr["required"]){
			throw new Exception("Please cd to a different directory!!\n\n\nError: Unable to find required attribute $attr[name] in $file[path]");
			//trigger_error("Unable to find required attribute $attr[name] in $file[path]" ,E_USER_WARNING);
		}
	}

	if($debug_data_process) {
		echo "= About to save \$file to the array. What does our file equal?\n";
		print_r($file);
		echo "\n\n";
	}
	$files[] = $file;

	if(++$limit_counter >= $max_results)
		throw new Exception("Unable to process all of the results given - \$limit_counter limit counter >= \$max_results max results");
}



// ignore anything inside of an _organize folder....? (pre-organized)

// deal with problem files

// setup replacement patterns

$new_dir_template = "{%parent_folder%}_f8organize/{%day%}/{%track%}/";
echo "new dir template is: $new_dir_template\n";



// setup the destinations
$destinations = array(
	// abs_dest_path = array()...
	// anticipate collisonsvb  
);

function sanitize($str){
	return preg_replace("/[^a-zA-Z0-9_.() ]/", "_",$str);
}

$limit_counter = 0;
foreach($files as $file){
	print_r($file);
	$new_dir = $new_dir_template;
	echo "\n\n";
	echo "=--=--=-=-=- LETs dO iT!\n";
	echo "$new_dir\n";
	foreach($file as $key => $value){
		$new_dir = str_replace("{%".$key."%}", sanitize($value), $new_dir);
	}
	echo "$new_dir\n";
	$new_key = $new_dir.$file['name'];
	if(isset($destinations[$new_key])){
		throw new Exception("Collision! Two files were too similar and would be renamed the same thing which would be very bad. new_dir $new_dir");
	} else {
		$destinations[$new_key] = array(
			"old_path"=>$file['path'],
			"name"=>$file['name'],
			"new_dir" => $new_dir,
		);
		$file;
	}
	if(++$limit_counter >= 20000000)
		break;
}


// require a "commit" argument from CLI before continuing

$first_arg_from_cli = @$argv[2];

if(!$first_arg_from_cli) {
	echo "****** PREVIEW *****\n";
	echo "> demos: \n";
	$i = 0;
	foreach($destinations as $destination){
		echo "\t(old:) $destination[old_path]\n";
		echo "\t(new:) $destination[new_dir]$destination[name]\n";
		echo "\n";
		if(++$i > 12)
			break;
	}
	if($i == 0)
		echo "\t(no demo)\n";
	echo "> Total files: ".count($files)."\n";
	echo "> Total destinations: ".count($destinations)."\n";
	echo "> new_dir_template: ".$new_dir_template."\n";
	echo "> working directory: ".$wd."\n";
	echo "\n";
	echo "You might want to cd to a different directory or delete files if you're unhappy with these results.\n";
	echo "If you're ready to continue, please run `f8organize commit`. Never run commit without checking the results first.\n\n(script terminated)\n";
	exit;
}
if($first_arg_from_cli != "commit"){

}



foreach($destinations as $destination){
	if(!is_dir($destination['new_dir'])){
		echo "> mkdir ".$destination['new_dir']."\n";
		if(!mkdir($destination['new_dir'], 0777, true)){
			throw new Exception("Unable to create directory! $destination[new_dir] destionation[new_dir] couldn't be mkdir'd");
		}
	}
	echo "> rename ".$destination['old_path']." --> ".$destination['new_dir']."".$destination['name']."\n\n";
	if(!rename($destination['old_path'],$destination['new_dir']."".$destination['name'])){
		throw new Exception("Drat! Unable to rename the old path to new_dir and name. Aw shucks.");
	}
}


echo "\n\n\nend -- the script has run to completion naturally (no E_USER_WARNINGs, thrown exceptions, fatal errors, or terminations)\n\n\n";







function newFile($path){
	$file = array(
		"path" => $path,
		"dir" => pathinfo($path,PATHINFO_DIRNAME),
		"name" => pathinfo($path,PATHINFO_BASENAME),
	);
	return $file;
}