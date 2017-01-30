<?php

class Node
{
    protected $customID;
    protected $class;
    protected $tag;
    protected $id;
    protected $nodeName;
    protected $nodeNameShort;
    protected $childrenNodesObj = array();
    protected $childrenID       = array();
    protected $parentID;
    protected $inlineStyles;
    protected $styles;

    public function __construct($customID, $tag, $nodeName, $class = null, $id = null, $inlineStyles = null)
    {
        $this->customID      = $customID;
        $this->tag           = $tag;
        $this->nodeName      = $nodeName[0];
        $this->nodeNameShort = $nodeName[1];
        $this->class         = $class;
        $this->id            = $id;
        $this->inlineStyles  = $inlineStyles;
    }
    public function setStyles($styles)
    {
        $this->styles = $styles;
    }
    public function setParentID($int)
    {
        $this->parentID = $int;
    }
    public function setChildrenID($arr)
    {
        if (is_array($arr) && count($arr) > 0) {
            $this->childrenID = $arr;
        } else {
            $this->childrenID = null;
        }
    }
    public function setChildrenNodesObj($input)
    {
        if (is_array($input) && count($input) > 0) {
            $this->childrenNodesObj = $input;
        } else {
            $this->childrenNodesObj = null;
        }
    }
    public function hasChildren()
    {
        if ($this->childrenNodesObj == null) {
            return false;
        } else {
            return true;
        }
    }
    public function getChildrenNodesObj()
    {
        return $this->childrenNodesObj;
    }
    public function getParentID()
    {
        return $this->parentID;
    }
    public function getCustomID()
    {
        return $this->customID;
    }
    public function getClass()
    {
        $classes = preg_split("/\s/", trim($this->class));
        if (count($classes) > 1) {
            $singleLine = "";
            for ($i=0; $i < count($classes); $i++) { 
                $singleLine .= "." . $classes[$i]; 
            }
            return $singleLine;
        } else {
            if (trim($this->class) != "") {
                return "." . $this->class;
            } else {
                return $this->class;
            }
        }
    }
    public function getClassList()
    {
        $classes = preg_split("/\s/", trim($this->class));
        if (count($classes) > 1) {
            for ($i=0; $i < count($classes); $i++) { 
                $classes[$i] = "." . $classes[$i]; 
            }
            return $classes;
        } else {
            if (trim($this->class) != "") {
                return array("." . $this->class);
            } else {
                return $this->class;
            }
        }
    }
    public function getTag()
    {
        return $this->tag;
    }
    public function getId()
    {
        return "#" . $this->id;
    }
    public function getNodeName()
    {
        return $this->nodeName;
    }
    public function getNodeNameShort()
    {
        return $this->nodeNameShort;
    }
    public function getChildrenID()
    {
        return $this->childrenID;
    }
    public function getInlineStyles()
    {
        return $this->inlineStyles;
    }
    public function getStyles()
    {
        return $this->styles;
    }
    public function printStyles($indent)
    {
        if ($this->styles == null) {
            return "";
        }
        $returnStyles="";
        if ($this->inlineStyles != null) {
            $printStyles = array_merge($this->styles, $this->inlineStyles);
        } else {
            $printStyles = $this->styles;
        }
        foreach ($printStyles as $key => $value) {
            $styles = preg_split("/;/", trim($value));
            foreach ($styles as $style) {
                if (trim($style) != "") {
                    $returnStyles .= $indent . trim($style) . ";\n";
                }
            }
        }
        return $returnStyles;
    }
}