<?php

$html = readline('Enter the name of the HTML file: ');
$extraCSS = readline('Enter the name of the CSS file (if any): '); 

if (isset($html) == false || trim($html) == '') {
    exit("Invalid HTML File. Exiting.");
} elseif (trim(file_get_contents(trim($html))) == '') {
    exit("HTML File is Empty. Exiting.");
}

if (trim($extraCSS) != '' && file_exists(trim($extraCSS)) == false) {
    exit("Invalid CSS File. Exiting.");
} elseif (trim($extraCSS) != '' && trim(file_get_contents(trim($extraCSS))) == '') {
    exit("CSS File is Empty. Exiting.");
}

$extraCSS = trim($extraCSS);
$html = file_get_contents(trim($html));

require_once('NodeTree.php');
require_once('Node.php');

$SassVert_Converter = new SassVert_Converter($html);

$SassVert_Converter->setCSSFile($extraCSS);

$SassVert_Converter->PrintSCSS();