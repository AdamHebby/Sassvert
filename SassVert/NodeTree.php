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
    protected $defaultConfigFile = "SassVert-config.json";
    public $conf;
    
    /**
     * TODO - Allow whole directory to be processed (find .html files)
     */

    public function __construct()
    {
    }


    /**
     * Sets up the HTML
     */
    public function setHTML($html)
    {
        $html           = $this->stripComments($html);
        $html           = $this->stripPHP($html);
        $this->tree     = $this->generateHTMLTree($html);
        $this->nodeTree = $this->generateNodes($this->tree);
        unset($this->tree);
        $this->linkChildren();
        $this->makeSCSSFile();
    }

    /**
     * Loads configuration file - loads default if not passed in
     */
    public function loadConfigFile($configFile = false)
    {
        if ($configFile == false) {
            $configFile = $this->defaultConfigFile;
        }

        if (@fopen($configFile, 'r') !== false) {
            $configFile = file_get_contents($configFile);

            if (is_array($configFile) !== false) {
                $this->loadConfigAsArray($configFile);
            } elseif($this->isValidJson($configFile) !== false) {
                $this->loadConfigAsJson($configFile);
            } else {
                die("Unable to load config file as an array or JSON \n");
            }

        } else {
            die("Unable to load config file ({$configFile}) \n");
        }
    }

    /**
     * Load the config as an array
     */
    public function loadConfigAsArray($confArray)
    {
        if (is_array($confArray) !== true) {
            die("Invalid Config - Not an array! \n");
        }
        $this->conf = $confArray;
        return true;
    }

    /**
     * Load the config as JSON, strip comments and convert to array
     */
    public function loadConfigAsJson($confJson)
    {
        if ($this->isValidJson($confJson) !== true) {
            die("Invalid Config - Not Valid JSON! \n");
        }
        $this->conf = json_decode(json_minify($confJson), true);
        return true;
    }

    /**
     * Checks if the JSON is valid - allows comments
     */
    private function isValidJson($strJson) {
        json_decode(json_minify($strJson)); 
        return (json_last_error() === JSON_ERROR_NONE); 
    }
    
    /**
     * Remove <!-- HTML Comments -->
     */
    public function stripComments($html) 
    {
        $html = preg_replace("<!--(.*?)-->", "", $html);
        return $html;
    }
    
    /**
     * Remove PHP
     */
    public function stripPHP($html)
    {
        $html = preg_replace(
            array('/<(\?|\%)\=?(php)?/', '/(\%|\?)>/'), 
            array('', ''), 
            $html);
        return $html;
    }
    
    /**
     * For Debugging purposes only
     */
    public function DumpNodeTree()
    {
        var_dump($this->nodeTree);
    }
    
    /**
     * Sets up CSS file after initialising
     */
    public function setCSS($css)
    {
        if (trim($css) !== '') {
            $StyleSheetArray   = $this->splitCSSFile($css);
            $this->cssStyles   = $StyleSheetArray[0];
            $this->mediaStyles = $StyleSheetArray[1];
            $this->parseCSS();
            $this->linkCSS();
        } else {
            echo "Error: Invalid CSS passed to setCSS() \n";
        }
    }
    
    /**
     * Find Comma Seperated CSS Values (CSCssV), then split
     */
    private function parseCSS()
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
    
    /**
     * Give Each HTML Node their styles
     */
    private function linkCSS()
    {
        foreach ($this->nodes as $customID => $node) {
            if ($node->getParentID() != null) {
                $parentNode = $this->getNode($node->getParentID());
            } else {
                $parentNode = null;
            }
            $selectors = $this->getSelectors($node, $parentNode);
            $styles    = $this->findMatchingStyles($selectors);
            $node->setStyles($styles[0]);
            $node->setMediaStyles($styles[1]);
        }
    }

    /**
     * Outputs SCSS to file
     */
    public function outputSCSS($nodeTree = null, $useNodeNameB = false)
    {
        if ($nodeTree == null) {
            $nodeTree = $this->nodeTree;
        } elseif (is_array($nodeTree) == false) {
            $nodeTree = array(
                $nodeTree
            );
        }
        foreach ($nodeTree as $node) {
            if ($useNodeNameB === true) {
                $nodeName = $node->getNodeNameB();
            } else {
                $nodeName = $node->getNodeNameShort();
            }
            fwrite($this->outputSCSSFile, $this->indent . $nodeName . " {\n");
            $this->indentAdd();
            fwrite($this->outputSCSSFile, $node->printStyles($this->indent) . "\n");
            fwrite($this->outputSCSSFile, $node->printMediaStyles($this->indent));
            if ($node->hasChildren()) {
                $nodeList = array();
                foreach ($node->getChildrenNodesObj() as $child) {
                    $nodeName = $child->getNodeNameShort();
                    if (in_array($nodeName, $nodeList)) {
                        $foundUnique = false;
                        while ($foundUnique != true) {
                            $pos = strpos($child->getNodeName(), $nodeName);
                            if ($pos !== false) {
                                $nodeNameB = substr_replace($child->getNodeName(), "", $pos, strlen($nodeName));
                            }
                            if (trim($nodeNameB) != '' && trim($nodeNameB) !== null && 
                                in_array(trim($nodeNameB), $nodeList) == false) {
                                $child->setNodeNameB(trim($nodeNameB));
                                $this->outputSCSS($child, true);
                                $foundUnique = true;
                            } else {
                                break;
                            }
                        }
                        
                    } else {
                        $nodeList[] = $nodeName;
                        $this->outputSCSS($child);
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

    /**
     * Increases indentation level
     */
    private function indentAdd()
    {
        $indent = $this->conf["indentation_type"];
        if ($indent === "tab") {
            $this->indent .= "\t";
        } else {
            $this->indent .= str_repeat(' ', $indent);
        }
    }

    /**
     * Decreases indentation level
     */
    private function indentTake()
    {
        $indent = $this->conf["indentation_type"];
        if ($indent === "tab") {
            $this->indent = substr($this->indent, 0, -1);
        } else {
            $this->indent = substr($this->indent, 0, -$indent);
        }
    }

    /**
     * Generates SCSS file
     */
    private function makeSCSSFile()
    {

        $filename = $this->conf["output_file_name"];
        $directory = trim(pathinfo($filename, PATHINFO_DIRNAME), '.');
        $ext      = "." . pathinfo($filename, PATHINFO_EXTENSION);
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        $directory = rtrim($directory, '/') . '/';

        if (file_exists($directory) !== true && $directory !== '') {
            mkdir($directory);
        }

        $fileCount = 1;
        while (file_exists("{$directory}{$filename}_{$fileCount}{$ext}") == true) {
            $fileCount++;
        }
        if ($this->conf["overwrite_output"] == false) {
            $file = fopen("{$directory}{$filename}_{$fileCount}{$ext}", "w");
            $this->outputSCSSFile = $file;
        } else {
            $fileCount = ($fileCount == 1) ? 1 : $fileCount -= 1;
            $file = fopen("{$directory}{$filename}_{$fileCount}{$ext}", "w");
            $this->outputSCSSFile = $file;
            return false;
        }
    }

    /**
     * Gets all possible selectors foreach HTML node
     */
    private function getSelectors($node, $parentNode)
    {
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

    /**
     * Grabs styles that match selectors
     */
    private function findMatchingStyles($selectors)
    {
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

    /**
     * Remove Media queries from CSS file
     */
    private function removeMediaQueries($cssIn)
    {
        $re     = '~@media\b[^{]*({((?:[^{}]+|(?1))*)})~';
        $cssOut = preg_replace($re, "", $cssIn);
        return trim($cssOut);
    }

    /**
     * Returns styles in array from css block array("body" => "width: 100%;")
     */
    private function returnStyles($stylesList)
    {
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

    /**
     * Grabs media queries from css block
     */
    private function getMediaQueries($css)
    {
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

    /**
     * Splits Generic CSS and Media Queries into 2 parts for easier parsing 
     */
    public function splitCSSFile($css)
    {
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

    /**
     * Gives parents their children
     */
    private function linkChildren()
    { 
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

    /**
     * Generates Objects from HTML
     */
    private function generateNodes($node)
    { 
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

    /**
     * Gets a node name for HTML block. <img ...> -> img | <img class="img" ...> -> .img
     */
    private function getNodeName($node)
    { 
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

    /**
     * Returns object based on Custom ID
     */
    private function getNode($id)
    { 
        foreach ($this->nodes as $customID => $node) {
            if ($node->getCustomID() === $id) {
                return $node;
            }
        }
    }

    /**
     * Generates simple html tree from HTML
     */
    private function generateHTMLTree($input)
    { 
        $tree = $this->html_to_tree($input);
        if ($tree["tag"] === 'html') {
            $tree = array_filter($tree["children"], function($ar)
            {
                return ($ar['tag'] == 'body');
            });
        }
        return $tree;
    }

    /**
     * Generates simple html tree from HTML
     */
    private function html_to_tree($html)
    { 
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

    /**
     * Generates html from DOM Document (html)
     */
    private function node_to_obj($element)
    { 
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

