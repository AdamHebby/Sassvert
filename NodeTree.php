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

    public function __construct($html)
    {
        $html           = $this->stripComments($html);
        $html           = $this->stripPHP($html);
        $this->tree     = $this->getTree($html);
        $this->nodeTree = $this->generateNodes($this->tree);
        $this->linkChildren();
    }
    function stripComments($html)
    {
        $html = preg_replace("<!--(.*?)-->", "", $html);
        return $html;
    }
    function stripPHP($html)
    {
        $html = preg_replace(array('/<(\?|\%)\=?(php)?/', '/(\%|\?)>/'), array('',''), $html);
        return $html;
    }
    public function DumpNodeTree()
    {
        var_dump($this->nodeTree);
    }
    public function setCSSFile($css)
    {
        if ($css != null || $css != "") {
            $StyleSheetArray   = $this->splitCSSFile($css);
            $this->cssStyles   = $StyleSheetArray[0];
            $this->mediaStyles = $StyleSheetArray[1];
            $this->parseCSS();
            $this->linkCSS();
            // $this->makeSCSSFile();
        }
    }
    function parseCSS()
    {
        $cssStyles = $this->cssStyles;
        foreach ($cssStyles as $key => $value) {
            if (preg_match("/((?=,)|(?=, ))/", $key)) {
                $newKeys = split(",", trim($key));
                foreach ($newKeys as $newKey) {
                    $cssStyles[trim($newKey)] = $value;
                }
                unset($cssStyles[$key]);
            }
        }
        $this->cssStyles = $cssStyles;

        $mediaStyles = $this->mediaStyles;
        foreach ($mediaStyles as $media => $mediaBlock) {
            foreach ($mediaBlock as $mKey => $mStyle) {
                if (preg_match("/((?=,)|(?=, ))/", $mKey)) {
                    $newMsKeys = split(",", trim($mKey));
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
    {
        foreach ($this->nodes as $customID => $node) {
            if ($node->getParentID() != null) {
                $parentNode = $this->getNode($node->getParentID());
            } else {
                $parentNode = null;
            }
            $selectors = $this->getSelectors($node, $parentNode);
            $styles    = $this->findMatchingStyles($selectors); // TODO
            $node->setStyles($styles); 
        }
    }
    public function PrintSCSS($nodeTree = null)
    {
        if ($nodeTree == null) {
            $nodeTree = $this->nodeTree;
        }
        foreach ($nodeTree as $node) {
            echo $this->indent . $node->getNodeName() . " {\n";
            $this->indentAdd();
            echo $node->printStyles($this->indent) . "\n";
            if ($node->hasChildren()) {
                $this->PrintSCSS($node->getChildrenNodesObj());
                $this->indentTake();
                echo $this->indent . "} \n";
            } else {
                $this->indentTake();
                echo $this->indent . "} \n";
            }
        }
    }
    function indentAdd()
    {
        $this->indent .= "  ";
    }
    function indentTake()
    {
        $this->indent = substr($this->indent, 0, -2);
    }
    function makeSCSSFile()
    {
        $fileCount = "1";
        while (file_exists("GeneratedSCSS_$fileCount.scss") == true) {
            $fileCount++;
        }
        $file = fopen("GeneratedSCSS_$fileCount.scss", "w");
        echo "SCSS file made: GeneratedSCSS_$fileCount.scss\n";
        $this->outputSCSSFile = $file;
    }
    function getSelectors($node, $parentNode)
    {
        $selectors = array();
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
    {
        $cssStyles = $this->cssStyles;
        $styles = array();
        foreach ($selectors as $selector) {
            if (isset($cssStyles[$selector])) {
                $styles[$selector] = $cssStyles[$selector];
            }
        }
        return $styles;
    }
    function removeMediaQueries($cssIn)
    {
        $re     = '~@media\b[^{]*({((?:[^{}]+|(?1))*)})~';
        $cssOut = preg_replace($re, "", $cssIn);
        return trim($cssOut);
    }
    function returnStyles($stylesList)
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
    function getMediaQueries($css)
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
    function splitCSSFile($css)
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
    function linkChildren()
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
    function generateNodes($node)
    {
        if (is_array($node)) {
            if (isset($node["tag"])) {
                $tag                           = $node["tag"];
                $class                         = isset($node["class"]) ? $node["class"] : null;
                $id                            = isset($node["id"]) ? $node["id"] : null;
                $inlineStyles                  = isset($node["styles"]) ? $node["styles"] : null;
                $nodeName                      = $this->getNodeName($node);
                $newNode                       = new Node($this->count, $tag, $nodeName, $class, $id, $inlineStyles);
                $this->nodeNames[$this->count] = $nodeName;
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
    {
        if (is_array($node)) {
            $tag      = null;
            $class    = null;
            $id       = null;
            $nodeName = null;
            if (isset($node["tag"])) {
                $tag = $node["tag"];
            } else {
                return false;
            }
            if (isset($node["class"])) {
                $class    = "." . str_replace(" ", ".", $node["class"]);
                $nodeName = $class;
            } elseif (isset($node["id"])) {
                $id       = "#" . $node["id"];
                $nodeName = $id;
            } elseif (isset($tag)) {
                if ($tag == "input" && isset($node["type"])) {
                    $type = $node["type"];
                    $tag  = $tag . "[type=\"$type\"]";
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
    function getNode($id)
    {
        foreach ($this->nodes as $customID => $node) {
            if ($node->getCustomID() === $id) {
                return $node;
            }
        }
    }
    function getTree($input)
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
    function html_to_tree($html)
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
    
    function node_to_obj($element)
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