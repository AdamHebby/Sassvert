<?php

$html     = readline('Enter the name of the HTML file: ');
$extraCSS = readline('Enter the name of the CSS file (if any): ');

if (isset($html) == false || trim($html) == '') { // Check for valid 1st input
    exit("Invalid HTML File. Exiting. \n");
} elseif (file_exists(trim($html)) == false || trim(file_get_contents(trim($html))) == '') { // Check for valid HTML file
    exit("HTML File is Empty. Exiting. \n");
}

if (trim($extraCSS) != '' && file_exists(trim($extraCSS)) == false) { // User has defined CSS but it doesn't exist
    exit("Invalid CSS File. Exiting. \n");
} elseif (trim($extraCSS) != '' && trim(file_get_contents(trim($extraCSS))) == '') { // User has defined CSS but it's empty
    exit("CSS File is Empty. Exiting. \n");
}

$extraCSS = trim($extraCSS);
$html     = file_get_contents(trim($html));

require_once('NodeTree.php');
require_once('Node.php');

$SassVert_Converter = new SassVert_Converter($html); // Initiate Class

$SassVert_Converter->setCSSFile($extraCSS); // Include CSS file

$SassVert_Converter->PrintSCSS(); // Output SCSS