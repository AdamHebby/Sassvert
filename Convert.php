<?php

function html_to_obj($html)
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Turn off errors for loading the HTML
    $dom->loadHTML($html);
	$remove = [];
    $script = $dom->getElementsByTagName('script');
	foreach($script as $item) {
		$remove[] = $item;
	}
	foreach ($remove as $item) {
		$item->parentNode->removeChild($item); 
	}
    return element_to_obj($dom->documentElement);
}

function element_to_obj($element)
{
    $obj = array( "tag" => $element->tagName );

    if (isset($element->parentNode->attributes["class"]) && isset($element->parentNode->attributes["id"])) {
    	$obj["parentNode"] = array(
			"tag" => $element->parentNode->tagName,
			"class" => $element->parentNode->getAttribute("class"),
			"id" => $element->parentNode->getAttribute("id")
		);
    }
	
    foreach ($element->attributes as $attribute) {
        $obj[$attribute->name] = $attribute->value;
    }
    foreach ($element->childNodes as $subElement) {
        if ($subElement->nodeType != XML_TEXT_NODE) {
            $obj["children"][] = element_to_obj($subElement);
        }
    }
    return $obj;
}

function getNodeName($node)
{
	if (is_array($node)) {
		$tag = null;
		$class = null;
		$id = null;
		$nodeName = null;
		if (isset($node["tag"])) {
			$tag = $node["tag"];
		} else {
			return false;
		}
		if (isset($node["class"])) {
        	$class = "." . str_replace(" ", ".", $node["class"]);
        	$nodeName = $class;
        } elseif (isset($node["id"])) {
        	$id = "#" . $node["id"];
        	$nodeName = $id;
        } elseif (isset($tag)) {
        	if ($tag == "input" && isset($node["type"])) {
        		$type = $node["type"];
        		$tag = $tag . "[type=\"$type\"]";
        	}
    		$nodeName = $tag;
        }
        if (isset($nodeName)) {
        	return $nodeName;
        } else {
        	return false;
        }
	}
}

function getInlineStyles($style, $indent)
{
	$styles = explode(';', $style);
	$printStyles = null;
	$indent .= '  ';
	foreach ($styles as $line) {
		if (trim($line) !== '') {
			$printStyles .= $indent . trim($line) . ";\n";
		}
	}
	return $printStyles;
}

function returnStyles($stylesList)
{
	$result = array();
	$css = trim($stylesList);
	preg_match_all( '/(?ims)([a-z0-9\s\,\.\:#_\-]+)\{([^\}]*)\}/', $css, $arr);
	unset($arr[0]);

	for ($i=0; $i < count($arr[1]); $i++) { 
		$styles = "";
		$selector = trim($arr[1][$i]);
		if ($selector == '') {
			$selector = '*';
		}
		$style = trim($arr[2][$i]);
		$result[$selector] = trim($style);
	}
	return $result;
}

function removeMediaQueries($cssIn)
{
	$re = '~@media\b[^{]*({((?:[^{}]+|(?1))*)})~'; 
	$cssOut = preg_replace($re, "", $cssIn);
	return trim($cssOut);
}

function getMediaQueries($css)
{
	$re = '~@media\b[^{]*({((?:[^{}]+|(?1))*)})~'; 
	preg_match_all($re, $css, $matches, PREG_PATTERN_ORDER);

	$allMediaQueries = array();
	foreach ($matches[2] as $key => $value) {
		$result = returnStyles($value);
		$mediaBlock = explode('{', $matches[0][$key], 2);
		$mediaBlock = trim($mediaBlock[0]);
		$allMediaQueries[$mediaBlock] = $result;
	}
	return $allMediaQueries;
}

function getSheetStyles($extraCSS)
{
	if (file_exists($extraCSS)) {
		$css = file_get_contents($extraCSS);
	} else {
		return array();
	}
	$mediaQueries = getMediaQueries($css);
	$css = removeMediaQueries($css);
	preg_match_all( '/(?ims)([a-z0-9\s\,\.\:#_\-]+)\{([^\}]*)\}/', $css, $arr);
	$result = array();
	unset($arr[0]);

	for ($i=0; $i < count($arr[1]); $i++) { 
		$styles = "";
		$selector = trim($arr[1][$i]);
		if ($selector == '') {
			$selector = '*';
		}
		$style = trim($arr[2][$i]);
		$result[$selector] = trim($style);
	}
	$result = array($result, $mediaQueries);
	return $result;
}

function multiKeyExists(array $arr, $key) {
    if (array_key_exists($key, $arr)) {
        return true;
    }
    foreach ($arr as $element) {
        if (is_array($element)) {
            if (multiKeyExists($element, $key)) {
                return true;
            }
        }
    }
    return false;
}

function findMQStyles($stylesArray, $selector, $indent)
{
	$allMQ = '';
	foreach ($stylesArray as $key => $value) {
		if (isset($value[$selector])) {
			$allMQ .= $indent . $key . " {\n";
			$indent .= '  ';

			$stylesLines = explode(';', $value[$selector]);
			$result = "";
			foreach ($stylesLines as $line) {
				if (trim($line) != '') {
					$result.= $indent . trim($line) . ";\n";
				}
			}
			$allMQ .= $result;
			$indent = substr($indent, 0, -2);
			$allMQ .= $indent . "} \n";
		}
	}
	return $allMQ;
}

function advancedStyleFinder($node)
{
	global $StyleSheetArrayCSS;
	$nodeName = getNodeName($node);
	$moreStyles = '';
	if (isset($node["parentNode"]["class"])) {
		if (isset($StyleSheetArrayCSS["." . $node["parentNode"]["class"] . " " . $nodeName])) {
			$moreStyles .= $StyleSheetArrayCSS["." . $node["parentNode"]["class"] . " " . $nodeName];
		}
	}
	if (isset($node["parentNode"]["tag"])) {
		if (isset($StyleSheetArrayCSS[$node["parentNode"]["tag"] . " " . $nodeName])) {
			$moreStyles .= $StyleSheetArrayCSS[$node["parentNode"]["tag"] . " " . $nodeName];
		}
	}
	if (isset($node["parentNode"]["id"])) {
		if (isset($StyleSheetArrayCSS["#" . $node["parentNode"]["id"] . " " . $nodeName])) {
			$moreStyles .= $StyleSheetArrayCSS["#" . $node["parentNode"]["id"] . " " . $nodeName];
		}
	}
	return $moreStyles;
}

function getStyleSheetSelector($selector, $indent)
{
	global $StyleSheetArrayCSS;
	global $StyleSheetArrayMQ;
	$indent .= '  ';
	if (isset($selector) && count($StyleSheetArrayCSS) > 0) {
		$exists = multiKeyExists($StyleSheetArrayCSS, $selector);
		if ($exists == false) {
			return false;
		}
		// Normal CSS Styles
		$styles = $StyleSheetArrayCSS[$selector];
		$stylesLines = explode(';', $styles);
		$result = "";
		foreach ($stylesLines as $line) {
			if (trim($line) != '') {
				$result.= $indent . trim($line) . ";\n";
			}
		}

		// Media Query CSS Styles
		$mqStyles = $StyleSheetArrayMQ;
		$mqStylesLines = findMQStyles($mqStyles, $selector, $indent);

		if (trim($mqStylesLines) != '') {
			return $result . "\n" . $mqStylesLines;
		} else {
			return $result;
		}
	} else {
		return false;
	}
}

function displaySCSS($node, $indent = '')
{
	if (is_array($node)) {
		$nodeName = getNodeName($node);
		if ($nodeName == false) {
			$names = array();
			$ids = array();
			$id = null;
			$useIds = false;
			foreach ($node as $subNode) {
				if (isset($subNode["id"])) {
					$ids[] = $subNode["id"];
					$id = $subNode["id"];
				}
				$childrenNode = getNodeName($subNode);
				if ($childrenNode !== false && in_array($id, $ids) == false) {
					$ids[] = $id;
				}
				if ($childrenNode !== false && in_array($childrenNode, $names) == false) {
					$names[] = $childrenNode;
					$subNodes[] = $subNode;
				} elseif ($childrenNode !== false && in_array($childrenNode, $names)) {
					if (count($ids) > 1) {
						$useIds = true;
					}
				}
			}
			foreach ($subNodes as $subNode) {
				displaySCSS($subNode, $indent);
				if ($useIds) {
					foreach ($ids as $amp) {
						$styles = getStyleSheetSelector($amp, $indent);
						echo "
						$indent&#$amp {
							$styles
						}\n";
					}
				}
				echo $indent . "}\n";
			}
		}
		if (isset($nodeName) && $nodeName !== false) {
			echo $indent . $nodeName . " {\n";
			$adv = advancedStyleFinder($node);
			echo getInlineStyles($adv, $indent);
			$sheetStyles = getStyleSheetSelector($nodeName, $indent);
			if ($sheetStyles != false) {
				echo $sheetStyles;
			}
			if (isset($node["style"])) {
				$inlineStyles = getInlineStyles($node["style"], $indent);
				echo $inlineStyles . "\n";
			} else {
				echo "\n";
			}
		}
		if (isset($node["children"])) {
			$indent .= "  ";
			displaySCSS($node["children"], $indent);
		}
	}
}

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

$tree = html_to_obj($html);

if ($tree["tag"] === 'html') {
	$tree = array_filter($tree["children"], function($ar) {
	   return ($ar['tag'] == 'body');
	});
}

$StyleSheetArray = getSheetStyles($extraCSS);


$StyleSheetArrayCSS = $StyleSheetArray[0];
$StyleSheetArrayMQ = $StyleSheetArray[1];
unset($StyleSheetArray);

displaySCSS($tree, null); // Initiate
