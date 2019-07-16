<?php
/*
MINIGAL NANO
- A PHP/HTML/CSS based image gallery script

This script and included files are subject to licensing from Creative Commons (http://creativecommons.org/licenses/by-sa/2.5/)
You may use, edit and redistribute this script, as long as you pay tribute to the original author by NOT removing the linkback to www.minigal.dk ("Powered by MiniGal Nano x.x.x")

MiniGal Nano is created by Thomas Rybak

Copyright 2010 by Thomas Rybak
Support: www.minigal.dk
Community: www.minigal.dk/forum

Please enjoy this free script!
*/

// Do not edit below this section unless you know what you are doing!


//-----------------------
// Debug stuff
//-----------------------
	error_reporting(E_ERROR);
//	error_reporting(E_ALL);
//	error_reporting(0);
/*
	$mtime = microtime();
	$mtime = explode(" ",$mtime);
	$mtime = $mtime[1] + $mtime[0];
	$starttime = $mtime;
*/

$version = "0.3.5";
ini_set("memory_limit","1024M");

//-----------------------
// DEFINE VARIABLES
//-----------------------

// EDIT SETTINGS BELOW TO CUSTOMIZE YOUR GALLERY
$thumbs_pr_page         = "50"; //Number of thumbnails on a single page
$gallery_width          = "90%"; //Gallery width. Eg: "500px" or "70%"
$backgroundcolor        = "white"; //This provides a quick way to change your gallerys background to suit your website. Use either main colors like "black", "white", "yellow" etc. Or HEX colors, eg. "#AAAAAA"
$templatefile           = "mano"; //Template filename (must be placed in 'templates' folder)
$title                  = "PhanomCSS screenshots"; // Text to be displayed in browser titlebar
$folder_color           = "black"; // Color of folder icons: blue / black / vista / purple / green / grey

//LANGUAGE STRINGS
$label_home             = "Home"; //Name of home link in breadcrumb navigation
$label_new              = "New"; //Text to display for new images. Use with $display_new variable
$label_page             = "Page"; //Text used for page navigation
$label_all              = "All"; //Text used for link to display all images in one page
$label_noimages         = "No images"; //Empty folder text
define(label_loading, "Loading..."); //Thumbnail loading text

//ADVANCED SETTINGS
define("thumb_size", 120); //Thumbnail height/width (square thumbs). Changing this will most likely require manual altering of the template file to make it look properly! 
$display_exif           = 0;


$page_navigation = "";
$breadcrumb_navigation = "";
$thumbnails = "";
$new = "";
$images = "";
$exif_data = "";
$messages = "";

//-----------------------
// PHP ENVIRONMENT CHECK
//-----------------------
if (!function_exists('exif_read_data') && $display_exif == 1) {
	$display_exif = 0;
    $messages = "Error: PHP EXIF is not available. Set &#36;display_exif = 0; in config.php to remove this message";
}

//-----------------------
// FUNCTIONS
//-----------------------
function is_directory($filepath) {
	// $filepath must be the entire system path to the file
	if (!@opendir($filepath)) return FALSE;
	else {
		return TRUE;
		closedir($filepath);
	}
}

function padstring($name, $length) {
	global $label_max_length;
	if (!isset($length)) $length = $label_max_length;
	if (strlen($name) > $length) {
      return substr($name,0,$length) . "...";
   } else return $name;
}
function getfirstImage($dirname) {
	$imageName = false;
	$ext = array("jpg", "png", "jpeg", "gif", "JPG", "PNG", "GIF", "JPEG");
	if($handle = opendir($dirname))
	{
		while(false !== ($file = readdir($handle)))
		{
			$lastdot = strrpos($file, '.');
			$extension = substr($file, $lastdot + 1);
			if ($file[0] != '.' && in_array($extension, $ext)) break;
		}
		$imageName = $file;
		closedir($handle);
	}
	return($imageName);
}
function readEXIF($file) {
		$exif_data = "";
		$exif_idf0 = exif_read_data ($file,'IFD0' ,0 );
        $emodel = $exif_idf0['Model'];

        $efocal = $exif_idf0['FocalLength'];
        list($x,$y) = split('/', $efocal);
        $efocal = round($x/$y,0);
       
        $exif_exif = exif_read_data ($file,'EXIF' ,0 );
        $eexposuretime = $exif_exif['ExposureTime'];
       
        $efnumber = $exif_exif['FNumber'];
        list($x,$y) = split('/', $efnumber);
        $efnumber = round($x/$y,0);

        $eiso = $exif_exif['ISOSpeedRatings'];
               
        $exif_date = exif_read_data ($file,'IFD0' ,0 );
        $edate = $exif_date['DateTime'];
		if (strlen($emodel) > 0 OR strlen($efocal) > 0 OR strlen($eexposuretime) > 0 OR strlen($efnumber) > 0 OR strlen($eiso) > 0) $exif_data .= "::";
        if (strlen($emodel) > 0) $exif_data .= "$emodel";
        if ($efocal > 0) $exif_data .= " | $efocal" . "mm";
        if (strlen($eexposuretime) > 0) $exif_data .= " | $eexposuretime" . "s";
        if ($efnumber > 0) $exif_data .= " | f$efnumber";
        if (strlen($eiso) > 0) $exif_data .= " | ISO $eiso";
        return($exif_data);
}
function checkpermissions($file) {
	global $messages;
	if (substr(decoct(fileperms($file)), -1, strlen(fileperms($file))) < 4 OR substr(decoct(fileperms($file)), -3,1) < 4) $messages = "At least one file or folder has wrong permissions. Learn how to <a href='http://minigal.dk/faq-reader/items/how-do-i-change-file-permissions-chmod.html' target='_blank'>set file permissions</a>";
}
function scan($dir){
 if ($handle = opendir($dir)) {
    while (false !== ($file = readdir($handle))) {
        // 1. LOAD FOLDERS
        if (is_directory($dir . "/" . $file)) { 
            if ($file != "." && $file != ".." ) {
                checkpermissions($dir . "/" . $file); // Check for correct file permission
                $subfolder = scan($dir . "/" . $file);
                foreach ($subfolder as $key => $value) {
                    $files[] = $value;
                }
            }
        }   

        // 2. LOAD CAPTIONS
        if (file_exists($dir ."/captions.txt")) {
            $file_handle = fopen($dir ."/captions.txt", "rb");
            while (!feof($file_handle) ) {   
                $line_of_text = fgets($file_handle);
                $parts = explode('/n', $line_of_text);
                foreach($parts as $img_capts) {
                    list($img_filename, $img_caption) = explode('|', $img_capts);   
                    $img_captions[$img_filename] = $img_caption;
                }
            }
            fclose($file_handle);
        }

        // 3. LOAD FILES
        if ($file != "." && $file != ".." && $file != "folder.jpg") {
            // JPG, GIF and PNG
            if (preg_match("/.jpg$|.gif$|.png$/i", $file)) {
                //Read EXIF
                if ($display_exif == 1) $img_captions[$file] .= readEXIF($dir . "/" . $file);

                checkpermissions($dir . "/" . $file);
                $displayed_info = str_replace(array($_REQUEST["dir"] .'/', 'screenshots/'), '', $dir );
                $files[] = array (
                    "name" => $file,
                    "date" => filemtime($dir . "/" . $file),
                    "size" => filesize($dir . "/" . $file),
                    "html" => "<li>
                                <a href='" . $dir . "/" . $file . "' rel='lightbox[billeder]' title='" . $displayed_info . "/" . $file. "'>
                                    <img src='createthumb.php?filename=" . $dir . "/" . $file . "&amp;size=".thumb_size."' alt='".label_loading."' />
                                    <span>" . $displayed_info . "</span>
                                </a>
                                </li>");
            }
        }           
    }
  closedir($handle);
  } else die("ERROR: Could not open $dir for reading!");
  return $files;
}

if (!defined("GALLERY_ROOT")) define("GALLERY_ROOT", "");
$thumbdir = rtrim('screenshots' . "/" .$_REQUEST["dir"],"/");
$thumbdir = str_replace("/..", "", $thumbdir); // Prevent looking at any up-level folders
$currentdir = GALLERY_ROOT . $thumbdir;

//-----------------------
// READ FILES AND FOLDERS
//-----------------------
$files = scan($currentdir);



//-----------------------
// OFFSET DETERMINATION
//-----------------------
	$offset_start = ($_GET["page"] * $thumbs_pr_page) - $thumbs_pr_page;
	if (!isset($_GET["page"])) $offset_start = 0;
	$offset_end = $offset_start + $thumbs_pr_page;
	if ($offset_end > sizeof($dirs) + sizeof($files)) $offset_end = sizeof($dirs) + sizeof($files);

	if ($_GET["page"] == "all")
	{
		$offset_start = 0;
		$offset_end = sizeof($dirs) + sizeof($files);
	}

//-----------------------
// PAGE NAVIGATION
//-----------------------
if (!isset($_GET["page"])) $_GET["page"] = 1;
if (sizeof($dirs) + sizeof($files) > $thumbs_pr_page)
{
	$page_navigation .= "$label_page ";
	for ($i=1; $i <= ceil((sizeof($files) + sizeof($dirs)) / $thumbs_pr_page); $i++)
	{
		if ($_GET["page"] == $i)
			$page_navigation .= "$i";
			else
				$page_navigation .= "<a href='?dir=" . $_GET["dir"] . "&amp;page=" . ($i) . "'>" . $i . "</a>";
		if ($i != ceil((sizeof($files) + sizeof($dirs)) / $thumbs_pr_page)) $page_navigation .= " | ";
	}
	//Insert link to view all images
	if ($_GET["page"] == "all") $page_navigation .= " | $label_all";
	else $page_navigation .= " | <a href='?dir=" . $_GET["dir"] . "&amp;page=all'>$label_all</a>";
}

//-----------------------
// MENU
//-----------------------
$projects = GALLERY_ROOT . 'screenshots/';
$menu = '';
if ($handle = opendir($projects)) {
    while (false !== ($project = readdir($handle))) {
        if ($project != "." && $project != ".." && $project !=".DS_Store") {
            $menu .= "<a href='?dir=" . $project . "'>" . $project . "</a>";
        }
    } 
    closedir($handle);
}


//-----------------------
// DISPLAY FILES
//-----------------------
for ($i = $offset_start - sizeof($dirs); $i < $offset_end && $offset_current < $offset_end; $i++) {
	if ($i >= 0) {
		$offset_current++;
		$thumbnails .= $files[$i]["html"];
	}
}

//Include hidden links for all images AFTER current page so lightbox is able to browse images on different pages
for ($y = $i; $y < sizeof($files); $y++) {	
	$page_navigation .= "<a href='" . $currentdir . "/" . $files[$y]["name"] . "' rel='lightbox[billeder]'  class='hidden' title='" . $img_captions[$files[$y]["name"]] . "'></a>";
}

//-----------------------
// OUTPUT MESSAGES
//-----------------------
if ($messages != "") {
$messages = "<div id=\"topbar\">" . $messages . " <a href=\"#\" onclick=\"document.getElementById('topbar').style.display = 'none';\";><img src=\"images/close.png\" /></a></div>";
}

//PROCESS TEMPLATE FILE
	if(GALLERY_ROOT != "") $templatefile = GALLERY_ROOT . "templates/integrate.html";
	else $templatefile = "templates/" . $templatefile . ".html";
	if(!$fd = fopen($templatefile, "r")) {
		echo "Template $templatefile not found!";
		exit();
	} else {
		$template = fread ($fd, filesize ($templatefile));
		fclose ($fd);
		$template = stripslashes($template);
		$template = preg_replace("/<% title %>/", $title, $template);
		$template = preg_replace("/<% messages %>/", $messages, $template);
		$template = preg_replace("/<% gallery_root %>/", GALLERY_ROOT, $template);
		$template = preg_replace("/<% images %>/", "$images", $template);
		$template = preg_replace("/<% thumbnails %>/", "$thumbnails", $template);
		$template = preg_replace("/<% menu %>/", $menu, $template);
		$template = preg_replace("/<% page_navigation %>/", "$page_navigation", $template);
		$template = preg_replace("/<% bgcolor %>/", "$backgroundcolor", $template);
		$template = preg_replace("/<% gallery_width %>/", "$gallery_width", $template);
		$template = preg_replace("/<% version %>/", "$version", $template);
		echo $template;
	}

//-----------------------
//Debug stuff
//-----------------------
/*   $mtime = microtime();
   $mtime = explode(" ",$mtime);
   $mtime = $mtime[1] + $mtime[0];
   $endtime = $mtime;
   $totaltime = ($endtime - $starttime);
   echo "This page was created in ".$totaltime." seconds";
*/
?>
