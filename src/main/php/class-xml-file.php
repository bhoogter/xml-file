<?php
/*
Module Name:  xml_file
Module Source: http://www.moreglory.net/
Description: A convenient XML file wrapper designed for datastore and retrieval.
Version: 1.0
Author: Benjamin Hoogterp
Author URI: http://www.BenjaminHoogterp.com/
License: MIT
*/
class xml_file
    {
    public $gid;
    public $Doc;
    public $XQuery;
    public $err;
    public $filename;
    public $mode;
    public $loaded;
    public $modified;
    public $readonly;
    public $notidy;
    public $sourceDate;
    public $saveMethod;
    
    function __construct()
        {
        $this->gid = uniqid("XMLFILE_");
        $this->clear();
        $n = func_num_args();
        $a = func_get_args();
        if ($n>=1)
            {
            if (is_string($a[0]))
                {
                if (substr($a[0],0,1)=="<") $this->loadXML($a[0]);
                else if (file_exists($a[0])) $this->load($a[0]);
                }
            if (is_object($a[0])) $this->loadDoc($a[0]);
            }
        else
            {
            }
        if ($n>=2)
            {
            if (strstr(strtolower($a[1]), "xml")) $this->mode='xml';
            if (strstr(strtolower($a[1]), "xhtml")) $this->mode='xhtml';
            if (strstr(strtolower($a[1]), "readonly")) $this->readonly=true;
            if (strstr(strtolower($a[1]), "notidy")) $this->notidy=true;
            }
        if ($n>=3)
            {
            if (is_string($a[2]))
                {
                if (substr($a[2],0,1)=="<") $this->transformXSL($a[2]);
                else if (file_exists($a[2])) $this->transform($a[2]);
                }
            }
        }
    function __destruct() 
        {
        unset($this->Doc);
        unset($this->XQuery);
        }
    function resolve_filename($fn)
        {
        if (file_exists($fn)) return $fn;
        if (file_exists($r="source/$fn")) return $r;
        return $fn;     //  nothing else to try....
        }
        
    public function clear()
        {
        unset($this->sourceDate);
        unset($this->Doc);
        unset($this->XQuery);
        $this->loaded=false;
        $this->filename="";
        $this->mode="";
        $this->modified=false;
        $this->readonly=false;
        $this->notidy = false;
        return false;
        }
    public function stat($Nnl=false,$Sht=false)
        {
        if (!$this->loaded) return "[NOT LOADED: ".$this->gid."]";
        if ($Sht)
            $s = "FN: ".$this->filename;
        else
            $s="<b>gid:</b> $this->gid\n<b>Filename:</b> $this->filename\n<b>Loaded:</b> $this->sourceDate" . ($this->can_save()?"\n<b>[CANSAVE]</b>":"") . ($this->readonly?"\n<b>[READ ONLY]</b>":"") . ($this->modified?"\n<b>[MODIFIED]</b>":"") . (isset($this->Doc)?"":"[NO DOC]");
        if (!!$Nnl) str_replace("\n", "  ", $s);
        return $s;
        }
    public function __toString() {return $this->stat();}
        
    private function init($D = 0)
        {
        $this->sourceDate = $D == 0 ? time() : $D;
        $this->loaded = isset($this->Doc);
        if (get_class($this->Doc) != "DOMDocument") self::backtrace("Invalid Object Type: " . get_class($this->Doc));
        $this->XQuery = $this->loaded ? new DOMXPath($this->Doc) : null;
        return $this->loaded;
        }
        
    function load($f)
        {
        $this->clear();
        $f = self::resolve_filename($f);
        if (!file_exists($f)) return false;
        $this->filename = $f;
        $this->Doc = new DomDocument;
        $res =  $this->Doc->load($f);
        if ($res === false) 
            {
            echo "<br />Failed to read: $f";
            self::backtrace("Failed to read: $f");
            return $this->clear();
            }
        $x = $this->init(filemtime($f));
        return $x;
        }
        
    function loadXML($x)
        {
        $this->clear();
        
        $this->Doc = new DomDocument;
        $res =  @$this->Doc->loadXML($x);
        
        $x = $this->init();
        return $x;
        }
    function loadDoc($D)
        {
        $this->clear();
        $this->Doc = $D;
        $x =  $this->init();
        return $x;
        }
    function can_save($f="") {return $this->loaded && ($f!="" || $this->filename != "") && !$this->readonly;}
    function saveXML($style="auto")
        {
        if (!isset($this->Doc))
            {
            self::backtrace();
            die("<br/><b><u>XMLFILE</u>::saveXML:</b> No Doc for save, $this");
            }
        $s = $this->Doc->saveXML();
        if (!$this->notidy) 
            $s = self::make_tidy_string($s, $style=="auto"?($this->mode||'xml'):$style);
        return $s;
        }
    function save($f="", $style="auto")
        {
        if (!$this->can_save($f)) return false;
        if ($f=="") $f = $this->filename;
        file_put_contents($f, $this->saveXML($style=="auto"?($this->mode||'xml'):$style));
        $this->modified = false;
        return true;
        }
        
    function query($Path) 
        {
        if ($Path=="") zoDie("No Path in XMLFILE::QUERY");
        if (!$this->loaded || $this->Doc == null) return "";//die("No file in XMLFILE::QUERY");
        if ($this->XQuery == null) $this->XQuery = new DOMXPath($this->Doc);
        if (($res = $this->XQuery->query($Path)) === false) debug_print_backtrace();
        return $res;
        }
    
    function fetch_node($Path)
        {
        if (($f = $this->query($Path)) == null) return;
        if ($f->length == 0) return null;
        return $f->item(0);
        }
    function root() { return $this->fetch_node("/"); }
    function node_string($Node) {return $this->Doc->saveXML($Node);}
    function part_string($Path)
        {
        if (($f = $this->query($Path)) == null) return;
        return ($f->length==0) ? "" : $this->node_string($f->item(0));
        }
    function part_string_list($Path)
        {
        if (($f = $this->query($Path)) == null) return array();
        $r = array();
        for ($i=0;$i<$f->length;$i++) 
            $r[$i] = $this->node_string($f->item($i));
        return $r;
        }
    function fetch_part($Path)
        {
        if (($f = $this->query($Path)) == null) return;
        if ($f == null) return "";
        return $f->length == 0?"":$f->item(0)->textContent;
        }
    function fetch_list($Path)
        {
        if (($f = $this->query($Path)) == null) return array();
        $r = array();
        for ($i=0;$i<$f->length;$i++) $r[$i] = $f->item($i)->textContent;
        return $r;
        }
    function fetch_nodes($Path)
        {
        if (($f = $this->query($Path)) == null) return array();
        $r = array();
        for ($i=0;$i<$f->length;$i++) $r[$i] = $f->item($i);
        return $r;
        }
    function count_parts($Path)
        {
        if (($f = $this->query($Path)) == null) return;
        $r = $f->length;
        return $r;
        }
        
    function map_attributes($Path)
        {
        }
    function get($p)        {   return $this->fetch_part($p);       }
    function set($p, $v)    {   return $this->set_part($p, $v);     }
    function lst($p)        {   return $this->fetch_list($p);       }
    function nod($p)        {   return $this->fetch_node($p);       }
    function nds($p)        {   return $this->fetch_nodes($p);      }
    function cnt($p)        {   return $this->count_parts($p);      }
    function def($p)        {   return $this->part_string($p);      }
    function map($p)        {   return $this->map_attributes($p);   }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    static function XMLToDoc($XML)
        {
        if ($XML=='') return null;
        $XML = self::make_tidy_string($XML);
        $D = new DOMDocument;
        $D->loadXML($XML);
        return $D;
        }
    static function FileToDoc($f)
        {
        $D = new DOMDocument;
        $D->load($f);
        return $D;
        }
    static function DocToXML($Doc)  {return $Doc->saveXML();}
    static function DocElToDoc($El)
        {
        $x = $El->ownerDocument->saveXML($El);
        return self::XMLToDoc($x);
        }
    static function transform_static($Doc, $f, $doRegister=true)
        {
        if (!file_exists($f)) return false;
        if (!$Doc) self::backtrace("NO DOC TO TO TRANSFORM IN xml_file::transform_static()");
        if (get_class($Doc)!="DOMDocument") self::backtrace("DOMDocument not supplied in xml_file::transform_static()");
        $xh = new XsltProcessor();
        $xsl = new DomDocument;
        $xsl->load($f);
        if ($doRegister) $xh->registerPHPFunctions();
        $xh->importStyleSheet($xsl);
        $D = $xh->transformToDoc($Doc);
        unset($xh);
        unset($xsl);
        return $D;
        }
    static function transformXSL_static($f, $XSL, $doRegister=true) 
        {
        if (!file_exists($f)) return false;
        if (!$Doc) self::backtrace("NO DOC TO TO TRANSFORM IN xml_file::transformXSL_static()");
        $xh = new XsltProcessor();
        $xsl = new DomDocument;
        $xsl->loadXML($XSL);
        if ($doRegister) $xh->registerPHPFunctions();
        $xh->importStyleSheet($xsl);
        $D = $xh->transformToDoc($Doc);
        unset($xh);
        unset($xsl);
        return $D;
        }
    static function transformXML_static($XML, $f, $doRegister=true)
        {return xml_file::transform_static(xml_file::XMLToDoc($XML), $f, $doRegister);}
    static function transformXMLXSL_static($XML, $XSL, $doRegister=true)
        {return xml_file::transformXSL_static(xml_file::XMLToDoc($XML), $XSL, $doRegister);}
    static function NodeToString($node, $part="all")
        {
        switch($part)
            {
            case "open":
                $ss = '<'.$node->nodeName;
                foreach($node->attributes as $att) $ss .= ' ' . $att->nodeName . "='" . str_replace("'",'"',$att->nodeValue) . "'";
                $ss .= $node->hasChildNodes()?">":" />";
                return $ss;
            case "contents":
                $ss = "";
                foreach($node->childNodes as $child)  $ss .= "\n".$child->ownerDocument->saveXML($child);
                return $ss;
            case "close":       return ($node->hasChildNodes()) ? "</".$node->nodeName.">" : '';
            default:        return $N->ownerDocument->saveXML($N);      // all
        }
    }
    function transform($f, $doRegister=true)
        {
        if (!file_exists($f)) return false;
        if (!$this->Doc) self::backtrace("NO DOC TO TO TRANSFORM IN xml_file::transform()");
        $xh = new XsltProcessor();
        $xsl = new DomDocument;
        $xsl->load($f);
        if ($doRegister) $xh->registerPHPFunctions();
        $xh->importStyleSheet($xsl);
        $this->Doc = $xh->transformToDoc($this->Doc);
        unset($xh);
        unset($xsl);
        return true;
        }
    function transformXSL($xsl, $doRegister=true)
        {
        $xh = new XsltProcessor();
        $xsl = new DomDocument;
        $xsl->loadXML($xsl);
        if ($doRegister) $xh->registerPHPFunctions();
        $xh->importStyleSheet($xsl);
        $this->Doc = $xh->transformToDoc($this->Doc);
        unset($xh);
        unset($xsl);
        return true;
        }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    static function xpathsplit($string){return self::qsplit("/",$string,"'",false);}
    static function qsplit($separator=",", $string, $delim="\"", $remove=true)
        {
        $elements = explode($separator, $string);
        for ($i = 0; $i < count($elements); $i++)
            {
            $nquotes = substr_count($elements[$i], $delim);
            if ($nquotes %2 == 1)
                {
                for ($j = $i+1; $j < count($elements); $j++)
                    {
                    if (substr_count($elements[$j], $delim) %2 == 1) 
                        {
                        // Put the quoted string's pieces back together again
                        array_splice($elements, $i, $j-$i+1, implode($separator, array_slice($elements, $i, $j-$i+1)));
                        break;
                        }
                    }
                }
                if ($remove && $nquotes > 0)
                    {
                    // Remove first and last quotes, then merge pairs of quotes
                    $qstr =& $elements[$i];
                    $qstr = substr_replace($qstr, '', strpos($qstr, $delim), 1);
                    $qstr = substr_replace($qstr, '', strrpos($qstr, $delim), 1);
                    $qstr = str_replace($delim.$delim, $delim, $qstr);
                    }
            }
        return $elements;
        }
//  Extends XPaths correctly
    static function extend_path($b, $l, $m)
        {
        if ($b=="") $b='/';
        if ($b[strlen($b)-1]!='/') $b=$b."/";
        if ($m=='@') $m = $b.'@'.$l;
        else if ($m=='') $m = $b.$l;
        else if ($m[0]!='/') $m = $b.$m;
        return $m;
        }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    static function has_field_accessor($part)           {   return strstr($part, "[*]")!==false;    }
    static function remove_field_accessor($part)        {   return str_replace("[*]","",$part); }
    static function replace_field_accessor($part, $val) {   return str_replace("[*]",$val,$part);   }
    static function add_field_accessor($part)
        {
        if (strpos($part, "[*]")===false)
            {
            $s = explode("/", $part);
            if (substr($s[count($s)-1],0,1)=="@")
                $s[count($s)-2]=$s[count($s)-2] . "[*]";
            else
                $s[count($s)-1]=$s[count($s)-1] . "[*]";
            $part = implode("/", $s);
            }
        return $part;
        }
        
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function replace_content($node, $value, $allow_delete=true)
        {
        $dom = $node->ownerDocument;
        $newnode = $dom->createElement($node->tagName);
        if (strstr($value, "<")!==false || strstr($value, ">")!==false || strstr($value, "\n")!==false)
            $newt = $dom->createCDATASection($value);
        else
            $newt = $dom->createTextNode($value);
        
        if ($node->hasChildNodes())
            for ($i=$node->childNodes->length - 1;$i>=0; $i--)
                {
                $c = $node->childNodes->item($i);
                if ($c->nodeType == XML_TEXT_NODE || $c->nodeType == XML_CDATA_SECTION_NODE) $node->removeChild($c);
                }
        
        if (! ($allow_delete && $value==""))
            $node->appendChild($newt);
        } 
    private function replace_attribute($node, $attr, $value, $allow_delete=true)
        {
        if ($node->nodeType == XML_ATTRIBUTE_NODE) $node = $node->parentNode;
        if ($node->hasAttribute($attr)) $node->removeAttribute($attr);
        if (!$allow_delete || $value != "")
            $node->setAttribute($attr, $value);
        return $value;
        }
        
    function delete_part($srcx) {return $this->delete_node($srcx);}
    function delete_node($srcx)
        {
        if (substr($srcx,strlen($srcx)-1,1) == "/") $srcx = substr($srcx, 0, strlen($srcx) - 1);
        $p = $this->fetch_node($srcx);
        if ($p == null) return;
        $k=$p->parentNode;
        if ($p->nodeType == XML_ATTRIBUTE_NODE)
            {
            $k->removeAttribute($p->nodeName);
            if (!$k->hasAttributes())
                {
                $k->parentNode->removeChild($k);
                }
            }
        else
            {
            $k->removeChild($p);
            }
        $this->modified=true;
        return true;
        }
        
    private function XPathAttribute($S, &$lvl, &$attr, &$val)
        {
        $lvl = $S;
        $attr = "";
        $val = "";
        
        $a = strpos($S, "[");
        if ($a===false) return false;
        $b = strpos($S, "]");
        
        $Sa = substr($S, 0, $a);
        $Sx = substr($S, $a+1, $b - $a - 1);
        if (is_numeric($Sx)) $Sx = "position()=$Sx";
        $Sy = explode("=", $Sx);
        if (count($Sy)==2)
            {
            $Sb = $Sy[0];
            $Sc = $Sy[1];
            }
        else return false;
        
        if (substr($Sb, 0, 1) == "@") $Sb = substr($Sb, 1);
        if (substr($Sc, 0, 1) == "'" && substr($Sc, strlen($Sc)-1) == "'" ||
            substr($Sc, 0, 1) == '"' && substr($Sc, strlen($Sc)-1) == '"')
                $Sc = substr($Sc, 1, strlen($Sc) - 2);
                
        $lvl = $Sa;
        $attr = $Sb;
        $val = $Sc;
        return true;
        }
    private function CreateXMLNode($srcx, $value="")
        {
        $parent = $this->root();
        
        $s = "";
        $xsx = $this->xpathsplit($srcx);
        foreach($xsx as $n=>$m)
            {
            $pre_s = $s;
            if (!($m == "" && $s == "")) $s = "$s/$m";
            if ($s=="") continue;
            
            $en = $this->query($s);
            if ($en->length == 0)
                {
                if ($m[0] == '@')
                    {
                    $this->replace_attribute($parent, substr($m, 1), $value, false);
                    }
                else
                    {
                    if (!$this->XPathAttribute($m, $a, $b, $c))
                        {
                        $dd = $this->Doc->createElement($m);
                        if ($n==count($xsx)-1)
                            $this->replace_content($dd, $value);
                        $parent->appendChild($dd);
                        }
                    else
                        {
                        $dd = $this->Doc->createElement($a);
                        if ($n==count($xsx)-1) $this->replace_content($dd, $value);
                        if ($b!="position()") $this->replace_attribute($dd, $b, $c, true);
                        $parent->appendChild($dd);
                        if ($b=="position()")
                            {
                            $dp = str_replace("$a"."["."position()=$c"."]", "$a", $s);
                            $d = $this->count_parts($dp);
                            $s = str_replace("$a"."["."position()=$c"."]", "$a"."["."position()=$d"."]", $s);
                            }
                        }
                    $parent=$dd;
                    }
                }
            else 
                $parent = $en->item(0);
            }
            
        $this->modified = true;
        return true;
        }
        
    function set_part($path, $value, $allow_delete=true)
        {
        $entries = $this->query($path);
        if ($entries == null) return false;
        if ($entries->length == 0)
            {
            if (!$allow_delete || $value != "")  // no delete if not existant
                $this->CreateXMLNode($path, $value);
            }
        else
            {
            $target = $entries->item(0);
            if ($target->nodeType == XML_ATTRIBUTE_NODE)
                {
                $p = $target->parentNode;
                $this->replace_attribute($target, $target->nodeName, $value);
                }
            else
                {
                $p = $target;
                $this->replace_content($target, $value);
                }
            if ($allow_delete && !$p->hasAttributes() && !is_object($p->firstChild))
                $p->parentNode->removeChild($p);
            }
        $this->modified = true;
        return true;
        }
    function adjust_part($path, $adj)
        {
        if ($adj===0) return;  // go no where
        if (substr($path,strlen($path)-1,1) == "/") $path= substr($path, 0, strlen($path) - 1);
        $entries = $this->query($path);
        if ($entries == null) return;
        if ($entries->length != 1)
            {
            if (substr($path, strlen($path)-1)=="/") return $this->adjust_part(substr($path, 0, strlen($path)-1), $adj);
            unset($D);
            return false;
            }
        $target = $entries->item(0);
        $NN = $target->nodeName;
        $x = $target->cloneNode(true);
        $parent = $target->parentNode;
        if ($adj=="top")
            {
            $parent->insertBefore($x, $parent->firstChild);
            $parent->removeChild($target);
            }
        else if ($adj=="bottom")
            {
            $parent->appendChild($x);
            $parent->removeChild($target);
            }
        if ($adj<0)
            {
            $px = $prev = $target;
            while ($adj<0)
                {
                if (($prev = $prev->previousSibling) == null) break; 
                $px = $prev;
                if ($px->nodeName == $NN) $adj++;
                }
            $parent->insertBefore($x, $px);
            $parent->removeChild($target);
            }
        else if ($adj > 0)
            {
            $next = $target;
            $adj++;
            while ($adj>0)
                {
                if (($next = $next->nextSibling) == null) break; 
                if ($next->nodeName == $NN) $adj--;
                }
            if ($next==null)
                $parent->appendChild($x);
            else
                $parent->insertBefore($x, $next);
            $parent->removeChild($target);
            }
        $this->modified = true;
        return true;
        }
    
        static function tidyXML_OPT()
            {
            $topt = array();
    
            $topt["wrap"]           = 0;
            $topt["input-xml"]      = true;
            $topt["output-xml"]     = true;
            $topt["add-xml-decl"]   = false;
            $topt["quiet"]      = true;
            $topt["fix-bad-comments"]   = true;
            $topt["fix-backslash"]  = true;
            $topt["tidy-mark"]      = false;
            $topt["char-encoding"]  = "raw";
            $topt["indent"]     = true;
            $topt["indent-spaces"]  = 4;
            $topt["indent-cdata"]   = false;
            $topt["add-xml-space"]  = true;
            $topt["escape-cdata"]   = false;
            $topt["write-back"]     = true;
            $topt["literal-attributes"] = true;
              
            $topt["force-output"]   = true;
            return $topt;
            }
        static function tidyXHTML_OPT()
            {
            $topt = array();
            $topt["input-xml"]      = false;
            $topt["output-xhtml"]   = true;
            $topt["output-xml"]     = false;
            $topt["markup"]     = true;
            $topt["new-empty-tags"] = "page, field, caption";
            $topt["add-xml-decl"]   = false;
//          $topt["add-xml-pi"]     = false;
            $topt["alt-text"]       = "Image";
            $topt["break-before-br"]    = true;
            $topt["drop-empty-paras"]   = false;
            $topt["fix-backslash"]  = true;
            $topt["fix-bad-comments"]   = true;
            $topt["hide-endtags"]   = false;
            $topt["char-encoding"]  = "raw";
            $topt["indent"]     = true;
            $topt["indent-spaces"]  = 2;
            $topt["indent-cdata"]   = false;
            $topt["escape-cdata"]   = false;
            $topt["quiet"]      = true;
            $topt["tidy-mark"]      = false;
            $topt["uppercase-attributes"]=false;
            $topt["uppercase-tags"] = false;
            $topt["word-2000"]      = false;
            $topt["wrap"]           = false;
            $topt["wrap-asp"]       = true;
            $topt["wrap-attributes"]    = true;
            $topt["wrap-jste"]      = true;
            $topt["wrap-php"]       = true;
            $topt["write-back"]     = true;
            $topt["add-xml-space"]  = true;
              
            $topt["force-output"]   = true;
            $topt["show-body-only"] = true;
    
            return $topt;
            }
        static function tidy_opt($style="xml") {return $style=="xhtml" ? self::tidyXHTML_OPT() : self::tidyXML_OPT();}
        static function make_tidy_string($str, $style="auto")
            {
            if ($style=="none") return $str;
            $tidy = new tidy;
            $tidy->parseString($str, self::tidy_opt($style));
            $tidy->CleanRepair();
            $s = $tidy->value;
            return self::tidy_cleanup($s, $style);
            }
        static function make_tidy_doc($D, $style="auto")
            {
            if ($style=="none") return $D;
            if (!$D) return "";
            $x = $D->saveXML();
            $x = self::make_tidy_string($x, $style);
            $x = str_replace("&nbsp;", "&#160;", $x);
            $x = self::tidy_cleanup($x, $style);
            $D = new DOMDocument;
            $D->loadXML($x);
            return $D;
            }
        static function make_tidy($filename, $style="xml")
            {
            if ($style=="none") return true;
            $tidy = new tidy;
            $tidy->parseFile($filename, self::tidy_opt($style));
            $tidy->CleanRepair();
            return file_put_contents($str, $tidy->value);
            }
        static function tidy_cleanup($s, $style='auto')             // when tidy makes a mess....
            {
// Tidy wont stop indenting CDATA, which adds extra line feeds at the beginning and end of of CDATA fields
// despite indent-cdata being set to false
            if ($style=="none") return $s;
            $s = preg_replace("/\n( )*<![[]CDATA[[](.*)[]][]]>\n/U", "<![CDATA[$2]]>", $s);
            switch($style)
                {
                case "xhtml":
                    $s = preg_replace("\$(<textarea([^>])*>)\n(.*)\n(</textarea>)\$U", "$1$3$4", $s);
                    break;
                case "xmlfragment":
                    $s = preg_replace("\$\<.xml (.)*?\>\$", "", $s);
                }
            return $s;
            }
		static function backtrace($m = '')
			{
			print "ERROR: $m";
			debug_print_backtrace();
			}
    }       // xml_file
?>