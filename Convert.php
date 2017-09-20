<?php

$htmlPrompt = "Enter the name of the HTML file: ";
$cssPrompt  = "Enter the name of the CSS file (if any): ";

$html = (isset($argv[1]) && $argv[1] !== '') ? $argv[1] : readline($htmlPrompt);
$css = (isset($argv[2]) && $argv[2] !== '') ? $argv[2] : readline($cssPrompt);

if (isset($html) == false || trim($html) == '') {
    // Check for valid 1st input
    exit("Invalid HTML File. Exiting. \n");
} elseif (
    file_exists(trim($html)) == false || 
    trim(file_get_contents(trim($html))) == '') {
     // Check for valid HTML file
    exit("HTML File is Empty. Exiting. \n");
}

// User has defined CSS but it doesn't exist
if (trim($css) !== '' && file_exists(trim($css)) == false) {
    exit("Invalid CSS File. Exiting. \n");
} elseif (trim($css) !== '' && trim(file_get_contents(trim($css))) == '') {
    // User has defined CSS but it's empty
    exit("CSS File is Empty. Exiting. \n");
}

$css  = trim($css);
$html = trim(file_get_contents(trim($html)));

require_once('NodeTree.php');
require_once('Node.php');

$SassVert_Converter = new SassVert_Converter($html); // Initiate Class

$SassVert_Converter->setCSSFile($css); // Include CSS file

$SassVert_Converter->PrintSCSS(); // Output SCSS