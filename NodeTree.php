<?php

class SassVert_Converter
{
    protected $tree;
    protected $nodes;
    protected $nodeNames;
    protected $count = 0;
    protected $cssStyles;
    protected $mediaStyles;
    protected $nodeTree;
    protected $outputSCSSFile;
    protected $indent = "";
    
    public function __construct($html )
    {
        $html           = $this->stripComments($html); // Remove <!-- HTML Comments -->
        $html           = $this->stripPHP($html); // Remove PHP
        $this->tree     = $this->getTree($html); // Generate heirarchy tree from DOM Nodes
        $this->nodeTree = $this->generateNodes($this->tree); // Make tree arrays into tree objects
        $this->linkChildren(); // Give parents their children!
        $this->makeSCSSFile(); // Make empty .scss file
    }
    function stripComments($html) 
    { // Remove <!-- HTML Comments -->
        $html = preg_replace("<!--(.*?)-->", "", $html);
        return $html;
    }
    function stripPHP($html)
    // Remove PHP
    {
        $html = preg_replace(array(
            '/<(\?|\%)\=?(php)?/',
            '/(\%|\?)>/'
        ), array(
            '',
            ''
        ), $html);
        return $html;
    }
    public function DumpNodeTree()
    // For Debugging
    {
        var_dump($this->nodeTree);
    }
    public function setCSSFile($css)
    // Sets up CSS file after initialising
    {
        if ($css != null || $css != "") {
            $StyleSheetArray   = $this->splitCSSFile($css);
            $this->cssStyles   = $StyleSheetArray[0];
            $this->mediaStyles = $StyleSheetArray[1];
            $this->parseCSS();
            $this->linkCSS();
        }
    }
    function parseCSS()
    // Find Comma Seperated CSS Values (CSCssV), then split
    {
        $cssStyles = $this->cssStyles;
        foreach ($cssStyles as $key => $value) {
            if (preg_match("/((?=,)|(?=, ))/", $key)) {
                $newKeys = preg_split("/,/", trim($key));
                foreach ($newKeys as $newKey) {
                    $cssStyles[trim($newKey)] = $value;
                }
                unset($cssStyles[$key]);
            }
        }
        $this->cssStyles = $cssStyles;
        
        // Same as above, except for Selectors in Media Query blocks
        $mediaStyles = $this->mediaStyles;
        foreach ($mediaStyles as $media => $mediaBlock) {
            foreach ($mediaBlock as $mKey => $mStyle) {
                if (preg_match("/((?=,)|(?=, ))/", $mKey)) {
                    $newMsKeys = preg_split("/,/", trim($mKey));
                    foreach ($newMsKeys as $newMsKey) {
                        $mediaStyles[$media][trim($newMsKey)] = $mStyle;
                    }
                    unset($mediaStyles[$media][$mKey]);
                }
            }
        }
        $this->mediaStyles = $mediaStyles;
    }
    function linkCSS()
    // Give Each HTML Node their styles
    {
        foreach ($this->nodes as $customID => $node) {
            if ($node->getParentID() != null) {
                $parentNode = $this->getNode($node->getParentID());
            } else {
                $parentNode = null;
            }
            $selectors = $this->getSelectors($node, $parentNode);
            $styles    = $this->findMatchingStyles($selectors); // TODO
            $node->setStyles($styles[0]);
            $node->setMediaStyles($styles[1]);
        }
    }
    public function PrintSCSS($nodeTree = null)
    { // Outputs SCSS to file
        if ($nodeTree == null) {
            $nodeTree = $this->nodeTree;
        } elseif (is_array($nodeTree) == false) {
            $nodeTree = array(
                $nodeTree
            );
        }
        foreach ($nodeTree as $node) {
            fwrite($this->outputSCSSFile, $this->indent . $node->getNodeName() . " {\n");
            $this->indentAdd();
            fwrite($this->outputSCSSFile, $node->printStyles($this->indent) . "\n");
            fwrite($this->outputSCSSFile, $node->printMediaStyles($this->indent));
            // Get MQ Styles
            if ($node->hasChildren()) {
                $nodeList = array();
                foreach ($node->getChildrenNodesObj() as $child) {
                    if (in_array($child->getNodeNameShort(), $nodeList)) {
                        // Break Down -- TODO
                        $this->PrintSCSS($child);
                    } else {
                        $nodeList[] = $child->getNodeNameShort();
                        $this->PrintSCSS($child);
                    }
                }
                $this->indentTake();
                fwrite($this->outputSCSSFile, $this->indent . "} \n");
            } else {
                $this->indentTake();
                fwrite($this->outputSCSSFile, $this->indent . "} \n");
            }
        }
    }
    function indentAdd()
    { // Increases indentation level
        $this->indent .= "  ";
    }
    function indentTake()
    { // Decreases indentation level
        $this->indent = substr($this->indent, 0, -2);
    }
    function makeSCSSFile()
    { // Generates SCSS file
        $fileCount = "1";
        while (file_exists("GeneratedSCSS_$fileCount.scss") == true) {
            $fileCount++;
        }
        $file = fopen("GeneratedSCSS_$fileCount.scss", "w");
        echo "SCSS file made: GeneratedSCSS_$fileCount.scss\n";
        $this->outputSCSSFile = $file;
    }
    function getSelectors($node, $parentNode)
    { // Gets all possible selectors foreach HTML node
        $selectors   = array();
        $selectors[] = $node->getClass();
        $selectors[] = $node->getTag() . $node->getClass();
        if ($node->getId() !== "#") {
            $selectors[] = $node->getId();
            $selectors[] = $node->getId() . $node->getClass();
            $selectors[] = $node->getTag() . $node->getId();
        }
        if ($parentNode !== null) {
            foreach ($selectors as $selector) {
                $selectors[] = $parentNode->getTag() . " " . $selector;
                if ($parentNode->getId() !== "#") {
                    $selectors[] = $parentNode->getId() . " " . $selector;
                }
                $selectors[] = $parentNode->getClass() . " " . $selector;
                $selectors[] = $parentNode->getTag() . $parentNode->getClass() . " " . $selector;
            }
        }
        $classList = $node->getClassList();
        if ($parentNode !== null) {
            $parentClassList = $parentNode->getClassList();
        }
        if (count($classList) > 1) {
            foreach ($classList as $class) {
                $selectors[] = $class;
                $selectors[] = $node->getTag() . $class;
                if ($node->getId() !== "#") {
                    $selectors[] = $node->getId() . $class;
                }
                if ($parentNode !== null) {
                    $selectors[] = $parentNode->getTag() . " " . $class;
                    $selectors[] = $parentNode->getClass() . " " . $class;
                    if ($parentNode->getId() !== "#") {
                        $selectors[] = $parentNode->getId() . " " . $class;
                    }
                    foreach ($parentClassList as $pClass) {
                        $selectors[] = $pClass . " " . $node->getTag();
                        if ($node->getId() !== "#") {
                            $selectors[] = $pClass . " " . $node->getId();
                        }
                        $selectors[] = $pClass . " " . $node->getClass();
                        $selectors[] = $pClass . " " . $class;
                    }
                }
            }
        }
        return $selectors;
    }
    function findMatchingStyles($selectors)
    { // Grabs styles that match selectors
        $cssStyles   = $this->cssStyles;
        $mqStyles    = $this->mediaStyles;
        $styles      = array();
        $mediaStyles = array();
        foreach ($selectors as $selector) {
            foreach ($mqStyles as $mediaParam => $mStyles) {
                if (isset($mStyles[$selector])) {
                    $mediaStyles[$mediaParam] = array(
                        $selector => $mStyles[$selector]
                    );
                }
            }
            if (isset($cssStyles[$selector])) {
                $styles[$selector] = $cssStyles[$selector];
            }
        }
        return array(
            $styles,
            $mediaStyles
        );
    }
    function removeMediaQueries($cssIn)
    { // Remove Media queries from CSS file
        $re     = '~@media\b[^{]*({((?:[^{}]+|(?1))*)})~';
        $cssOut = preg_replace($re, "", $cssIn);
        return trim($cssOut);
    }
    function returnStyles($stylesList)
    { // Returns styles in array from css block array("body" => "width: 100%;")
        $result = array();
        $css    = trim($stylesList);
        preg_match_all('/(?ims)([a-zA-Z0-9\s\,\.\:#_\-\*]+)\{([^\}]*)\}/', $css, $arr);
        unset($arr[0]);
        
        for ($i = 0; $i < count($arr[1]); $i++) {
            $selector = trim($arr[1][$i]);
            if ($selector == '') {
                $selector = '*';
            }
            $result[$selector] = trim($arr[2][$i]);
        }
        return $result;
    }
    function getMediaQueries($css)
    { // Grabs media queries from css block
        $re = '~@media\b[^{]*({((?:[^{}]+|(?1))*)})~';
        preg_match_all($re, $css, $matches, PREG_PATTERN_ORDER);
        
        $allMediaQueries = array();
        foreach ($matches[2] as $key => $value) {
            $result                       = $this->returnStyles($value);
            $mediaBlock                   = explode('{', $matches[0][$key], 2);
            $mediaBlock                   = trim($mediaBlock[0]);
            $allMediaQueries[$mediaBlock] = $result;
        }
        return $allMediaQueries;
    }
    function splitCSSFile($css)
    { // Splits Generic CSS and Media Queries into 2 parts for easier parsing 
        if (file_exists($css)) {
            $css = file_get_contents($css);
        } else {
            return array();
        }
        $mediaQueries = $this->getMediaQueries($css);
        $css          = $this->removeMediaQueries($css);
        preg_match_all('/(?ims)([a-zA-Z0-9\s\,\.\:#_\-\*]+)\{([^\}]*)\}/', $css, $arr);
        $result = array();
        unset($arr[0]);
        
        for ($i = 0; $i < count($arr[1]); $i++) {
            $styles   = "";
            $selector = trim($arr[1][$i]);
            if ($selector == '') {
                $selector = '*';
            }
            $style             = trim($arr[2][$i]);
            $result[$selector] = trim($style);
        }
        $result = array(
            $result,
            $mediaQueries
        );
        return $result;
    }
    function linkChildren()
    { // Gives parents their children
        foreach ($this->nodes as $customID => $node) {
            $childrenNodes = $node->getChildrenNodesObj();
            $children      = array();
            if ($childrenNodes !== null) {
                foreach ($childrenNodes as $nodeName) {
                    $children[] = $nodeName->getCustomID();
                }
            }
            $node->setChildrenID($children);
        }
    }
    function generateNodes($node)
    { // Generates Objects from HTML
        if (is_array($node)) {
            if (isset($node["tag"])) {
                $tag                           = $node["tag"];
                $class                         = isset($node["class"]) ? $node["class"] : null;
                $id                            = isset($node["id"]) ? $node["id"] : null;
                $inlineStyles                  = isset($node["styles"]) ? $node["styles"] : null;
                $nodeName                      = $this->getNodeName($node);
                $newNode                       = new Node($this->count, $tag, $nodeName, $class, $id, $inlineStyles);
                $this->nodeNames[$this->count] = $nodeName[0];
                $this->nodes[$this->count]     = $newNode;
                $this->count++;
                
                if (isset($node["children"])) {
                    $childrenNodeObj = array();
                    foreach ($node["children"] as $subNodes) {
                        $subNodes["parentNode"] = $newNode;
                        $cnode                  = $this->generateNodes($subNodes);
                        $childrenNodeObj[]      = $cnode;
                    }
                    foreach ($childrenNodeObj as $childObj) {
                        $childObj->setParentID($newNode->getCustomID());
                    }
                    $newNode->setChildrenNodesObj($childrenNodeObj);
                }
                return $newNode;
            } else {
                $childrenNodeObj = array();
                foreach ($node as $subNodes) {
                    $cnode             = $this->generateNodes($subNodes);
                    $childrenNodeObj[] = $cnode;
                }
                return $childrenNodeObj;
            }
        }
    }
    function getNodeName($node)
    { // Gets a node name for HTML block. <img ...> -> img | <img class="img" ...> -> .img
        if (is_array($node)) {
            $tag           = null;
            $class         = null;
            $id            = null;
            $nodeName      = null;
            $nodeNameShort = null;
            if (isset($node["tag"])) {
                $tag = $node["tag"];
            } else {
                return false;
            }
            if (isset($node["class"])) {
                $class         = "." . str_replace(" ", ".", $node["class"]);
                $nodeName      = $class;
                $short         = preg_split("/\s/", trim($node["class"]));
                $nodeNameShort = "." . $short[0];
            } elseif (isset($node["id"])) {
                $id            = "#" . $node["id"];
                $nodeName      = $id;
                $nodeNameShort = $id;
            } elseif (isset($tag)) {
                if ($tag == "input" && isset($node["type"])) {
                    $type = $node["type"];
                    $tag  = $tag . "[type=\"$type\"]";
                }
                $nodeName      = $tag;
                $nodeNameShort = $tag;
            }
            if (isset($nodeName)) {
                return array(
                    $nodeName,
                    $nodeNameShort
                );
            } else {
                return false;
            }
        }
    }
    function getNode($id)
    { // Returns object based on Custom ID
        foreach ($this->nodes as $customID => $node) {
            if ($node->getCustomID() === $id) {
                return $node;
            }
        }
    }
    function getTree($input)
    { // Generates simple html tree from HTML
        $tree = $this->html_to_tree($input);
        if ($tree["tag"] === 'html') {
            $tree = array_filter($tree["children"], function($ar)
            {
                return ($ar['tag'] == 'body');
            });
        }
        return $tree;
    }
    function html_to_tree($html)
    { // Generates simple html tree from HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Turn off errors for loading the HTML
        $dom->loadHTML($html);
        $remove = array();
        $script = $dom->getElementsByTagName('script');
        foreach ($script as $item) {
            $remove[] = $item;
        }
        foreach ($remove as $item) {
            $item->parentNode->removeChild($item);
        }
        return $this->node_to_obj($dom->documentElement);
    }
    
    function node_to_obj($element)
    { // Generates html from DOM Document (html)
        $obj = array(
            "tag" => $element->tagName
        );
        
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
                $obj["children"][] = $this->node_to_obj($subElement);
            }
        }
        return $obj;
    }
}