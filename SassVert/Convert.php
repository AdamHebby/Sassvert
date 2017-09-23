<?php

$htmlPrompt = "Enter the name of the HTML file: ";
$cssPrompt  = "Enter the name of the CSS file (if any): ";

$html = (isset($argv[1]) && $argv[1] !== '') ? $argv[1] : readline($htmlPrompt);
$css = (isset($argv[2]) && $argv[2] !== '') ? $argv[2] : readline($cssPrompt);

if (isset($html) == false || trim($html) == '') {
    // Check for valid 1st input
    exit("Invalid HTML File. Exiting. \n");
} elseif (
     // Check for valid HTML file
    file_exists(trim($html)) == false || 
    trim(file_get_contents(trim($html))) == '') {
    exit("HTML File is Empty. Exiting. \n");
}

if (trim($css) !== '' && file_exists(trim($css)) == false) {
	// User has defined CSS but it doesn't exist
    exit("Invalid CSS File. Exiting. \n");
} elseif (trim($css) !== '' && trim(file_get_contents(trim($css))) == '') {
    // User has defined CSS but it's empty
    exit("CSS File is Empty. Exiting. \n");
}

$css  = trim($css);
$html = trim(file_get_contents(trim($html)));

require_once('NodeTree.php');
require_once('Node.php');
require_once('minify.json.php');

$SassVert_Converter = new SassVert_Converter(); // Initiate Class

$SassVert_Converter->loadConfigFile('SassVert/SassVert-config.json');

$SassVert_Converter->setHTML($html); // Include CSS file
// $SassVert_Converter->setCSS($css); // Include CSS file


$SassVert_Converter->outputSCSS(); // Output SCSS