<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

/** A utility class for generating HTML and XML.
  *
  * @method string div(string $content, string $class, array|string $attrs) Generate a DIV tag.
  * @method string p(string $content, string $class, array|string $attrs) Generate a P tag.
  * @method string form(string $content, string $class, array|string $attrs) Generate a FORM tag.
  * @method string h1(string $content, string $class, array|string $attrs) Generate a H1 tag.
  * @method string h1(string $content, string $class, array|string $attrs) Generate a H2 tag.
  * @method string h2(string $content, string $class, array|string $attrs) Generate a H3 tag.
  * @method string h3(string $content, string $class, array|string $attrs) Generate a H4 tag.
  * @method string h4(string $content, string $class, array|string $attrs) Generate a H5 tag.
  * @method string h5(string $content, string $class, array|string $attrs) Generate a H6 tag.
  * @method string span(string $content, string $class, array|string $attrs) Generate a SPAN tag.
  * @method string cite(string $content, string $class, array|string $attrs) Generate a CITE tag.
  * @method string i(string $content, string $class, array|string $attrs) Generate an I tag.
  * @method string em(string $content, string $class, array|string $attrs) Generate an EM tag.
  * @method string bold(string $content, string $class, array|string $attrs) Generate a BOLD tag.
  * @method string strong(string $content, string $class, array|string $attrs) Generate a STRONG tag.
  * @method string pre(string $content, string $class, array|string $attrs) Generate a PRE tag.
  * @method string code(string $content, string $class, array|string $attrs) Generate a CODE tag.
  * @method string small(string $content, string $class, array|string $attrs) Generate a SMALL tag.
  * @method string br(array|string $attrs) Generate a BR tag.
  * @method string meta(array|string $attrs) Generate a META tag.
  * @method string link(array|string $attrs) Generate a LINK tag.
  * @method string embed(string $content, string $class, array|string $attrs) Generate a EMBED tag.
  * @method string hr(array|string $attrs) Generate a HR tag.
  * @method string param(array|string $attrs) Generate a PARAM tag.
  **/
class SimpleMarkup
{
    const NO_VALUE = "\0";
    protected $isXml = false;
    protected $isXhtml = false;
    protected $settable = array('isXml', 'isXhtml');
    
    protected $standardTags = array(
        'abbr', 'address', 'article', 'aside', 'audio', 'blockquote', 'body', 'bold',
        'canvas', 'cite', 'caption', 'code', 'col', 'colgroup', 'div', 'dd', 'dl', 'dt',
        'em', 'embed', 'fieldset', 'figcaption', 'figure', 'footer', 'form',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'html', 'i', 'iframe',
        'legend', 'link', 'main', 'mark', 'menu', 'menuitem', 'nav', 'noscript',
        'output', 'p', 'pre', 'progres', 'q',
        'section', 'small', 'script', 'span', 'strong', 'style', 'sub', 'summary', 'sup',
        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track',
        'u', 'var', 'video'
    );

    protected $selfClosingTags = array('br', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'wbr');
    
    /**
     * Magic method used to handle standard tags, falls back to parent method for getter/setter.
     * 
     * @param string Invoked method name.
     * @param array Arguments passed to the method.
    **/
    public function __call($method, $args)
    {
        if(in_array($method, $this->standardTags))
        {
            $result = $this->tag($args[0], $method, SimpleUtil::getValue($args, 1), null, SimpleUtil::getValue($args, 2));
        }
        else if(in_array($method, $this->selfClosingTags))
        {
            $result = $this->tag(null, $method, '', null, SimpleUtil::getValue($args, 0));
        }
        else if($this->isXml)
        {
            $result = $this->xml($args[0], $args[1], SimpleUtil::getValue($args, 2));
        }
        else
        {
            $result = false;
            //$result = parent::__call($method, $args);
        }
        
        return $result;
    }
    
    /**
     * Safely build up markup.
     * 
     * @param string The innerHTML of the node.
     * @param string Tag name to use when $url is empty.
     * @param string Class name to add to the tag.
     * @param string URL to create a link from.
     * @param array|string Any additional attributes to include in the tag.
     * @return string Markup or empty string if $text is empty.
    **/
    public function tag($content, $textNode = 'span', $class = '', $url = '', $attrs = '')
    {
        $tag = '';
        $noValue = $content === self::NO_VALUE;
        $implicit = in_array($textNode, $this->selfClosingTags);
        
        if($noValue){ $content = ''; }
        if($class){ $class = ' class="'.$class.'"'; }
        
        if($content || $implicit || $noValue){
            $tag = $url ? '<a href="'.$url.'"'.$class.$this->buildAttrs($attrs).'>'.$content.'</a>' :
                '<'.$textNode.$class.$this->buildAttrs($attrs).$this->closeTag().
                ($implicit ? '' : $content.'</'.$textNode.'>');
        }
        return $tag;
    }
    
    /**
     * Close tag using HTML or X(HT)ML style.
     * 
     * @return string Closing characters.
    **/
    protected function closeTag()
    {
        return ($this->isXml || $this->isXhtml ? '/' : '').'>';
    }
    
    /**
     * Build tag attributes.
     * 
     * @param $attrs,... (array|string) Variable number of attribute arguments.
     * @return string Attribute string.
    **/
    public function buildAttrs()
    {
        $attrs = '';
        $args = func_get_args();
        foreach($args as $arg){
            $attrs .= SimpleString::buildParams($arg, ' ', ' ', '=', array($this, 'handleAttrValue'));
        }
        return $attrs;
    }
    
    /**
     * Build attribute value.
     * 
     * @param string The raw attribute value.
     * @return string Escaped and quoted attribute value.
    **/
    public function handleAttrValue($value)
    {
        return '"'.addslashes($value).'"';
    }
    
    /**
     * Build a link tag.
     * 
     * @param string The content of the link.
     * @param string Tag url of the link.
     * @param string An optional class.
     * @access protected
    **/
    public function a($content, $url, $class = '', $attrs = '')
    {
        return $this->tag($content, '', $class, $url, $attrs = '');
    }
    
    /**
     * Build an image tag.
     * 
     * @param array|string An array containing `url`, `alt`, `width`, and `height` keys; or a string containing the image URL.
     * @param string The image class name.
     * @param array|string Additional attributes to include in the tag.
     * @return string Image tag or empty string if src is empty.
    **/
    public function image($data, $class = '', $attrs = '')
    {
        $attrs = '';
        if(is_string($data))
        {
            $data = array('url'=>$data);
        }
        
        $src = $this->value($data, 'url');
        if($src)
        {
            $alt = ' alt="'.$this->value($data, 'alt').'"';
            $width = $this->value($data, 'width');
            $height = $this->value($data, 'height');
            $dims = $width && $height ? ' width="'.$width.'" height="'.$height.'"' : '';
            $attrs = 'src="'.$src.'"'.$dims.$alt.$this->buildAttrs($attrs);
        }
        
        return $attrs ? $this->tag(null, 'img', $class, null, $attrs) : '';
    }
    
    /**
     * Build a list.
     * 
     * @param array The list data with each item as a string or an array containing keys 'content', 'class'.
     * @param string Optional class name.
     * @param string Optional attributes.
     * @param string List type.
     * @return string List markup or empty string if $data is empty.
    **/
    public function ul($list, $class = '', $attrs = '', $type = 'ul')
    {
        if(!is_array($list) || empty($list)){
            return '';
        }
        
        $items = '';
        for($l=0, $lc=count($list); $l<$lc; $l++)
        {
            $content = '';
            $itemClass = '';
            $itemAttrs = '';
            $item = $list[$l];
            
            if(is_string($item))
            {
                $content = $item;
            }
            else if(is_array($item))
            {
                $content = SimpleUtil::getValue($item, 'content');
                $itemClass = SimpleUtil::getValue($item, 'class');
                $itemAttrs = SimpleUtil::getValue($item, 'attrs');
            }
            
            if($content)
            {
                if($l == 0){ $itemClass = ($itemClass ? ' ' : '').'first'; }
                if($l == $lc-1){ $itemClass = ($itemClass ? ' ' : '').'last'; }
                $items .= $this->tag($content, 'li', $itemClass, null, $itemAttrs);
            }
        }
        
        return $this->tag($items, $type, $class, null, $attrs);
    }
    
    /**
     * Wrapper around ul to build an ordered list.
     * 
     * @see ul.
    **/
    public function ol($list, $class = '', $attrs = '')
    {
        return $this->ul($list, $class, $attrs, 'ol');
    }
    
    /**
     * Build a form label.
     * 
     * @param string The label value.
     * @param string Field name the label is for.
     * @param string Class name to add to the tag.
     * @param string Any additional attributes to include in the tag.
     * @return string Markup or empty string if $text is empty.
     * @access protected
    **/
    public function label($content, $for, $class='', $attrs='')
    {
        return $this->tag($content, 'label', $class, null, $this->buildAttrs($attrs, array('for'=>$for)));
    }
    
    /**
     * Build a form input.
     * 
     * @param string The input type.
     * @param string Class name to add to the tag.
     * @param string URL to create a link from.
     * @param string Any additional attributes to include in the tag.
     * @return string Markup or empty string if $text is empty.
     * @access protected
    **/
    public function input($type, $name, $value = '', $class='', $attrs='')
    {
        $attrs = ($name ? 'name="'.$name.'" ' : '').$this->buildAttrs($attrs, array('type'=>$type, 'value'=>$value));
        return $this->tag(null, 'input', $class, null, $attrs);
    }
    
    /**
     * Build a form button.
     * 
     * @param string The button value.
     * @param string Field name the label is for.
     * @param string Class name to add to the tag.
     * @param string Any additional attributes to include in the tag.
     * @return string Markup or empty string if $text is empty.
     * @access protected
    **/
    public function button($content, $type = 'button', $class = '', $attrs = '')
    {
        $attrs = $this->buildAttrs($attrs, array('type'=>$type));
        return $this->tag($content, 'button', $class, null, $attrs);
    }
    
    /**
     * Build a select.
     * 
     * @param array A hash where each key is used as the option value and the value is used as the option text. Each value may also be an array containing optional keys 'label', 'value', 'class', 'type', and 'attrs'. Arrays can be nested to create option groups. Each group must contain a 'label' for the group text and a 'value' containing an array of nested options.
     * @param string Optional selected value. May be an array for multiselects.
     * @param string Optional class name.
     * @param string Optional attributes.
     * @return string List markup or empty string if $data is empty.
    **/
    public function select($options, $name, $selected = '', $class = '', $attrs = '')
    {
        return $this->tag($this->parseOptions($options, $selected),
            'select', $class, null, $this->buildAttrs($attrs, array('name'=>$name)));
    }
    
    /**
     * Build a textarea.
     * 
     * @param array Field name.
     * @param string Optional value. Will be properly encoded (TODO).
     * @param string Optional class name.
     * @param string Optional attributes.
     * @return string Textarea markup.
    **/
    public function textarea($name, $value = '', $class = '', $attrs = '')
    {
        return $this->tag('textarea', $class, $value, $this->buildAttrs($attrs, array('name'=>$name)));
    }
    
    /**
     * Build option markup for select().
     * 
     * @param array Options, see select() for details.
     * @return string Options markup or empty string if $data is empty.
    **/
    protected function parseOptions($options, $selected = '')
    {
        if(!is_array($options) || empty($options)){
            return '';
        }
        
        $optionMarkup = '';
        foreach($options as $value => $option)
        {
            $label = '';
            $optionClass = '';
            $optionAttrs = '';
            $valueAttr = '';
            $selectedAttr = '';
            
            if(is_string($option))
            {
                $label = $option;
            }
            else if(is_array($option))
            {
                $value = SimpleUtil::getValue($option, 'value');
                $label = SimpleUtil::getValue($option, 'label');
                $optionClass = SimpleUtil::getValue($option, 'class');
                $optionAttrs = SimpleUtil::getValue($option, 'attrs');
            }
            
            if($label)
            {
                if(is_array($value))
                {
                    $attrs = $this->buildAttrs($optionAttrs, array('label'=>$label));
                    $content = $this->tag($this->parseOptions($value, $selected), 'optgroup', $optionClass, null, $attrs);
                } else {
                    $isSelected = is_array($selected) ? in_array($value, $selected, true) : $value === $selected;
                    $valueAttr = 'value="'.$value.'"';
                    $selectedAttr = $isSelected ? ' selected="selected"' : '';
                    $content = $label;
                }
                $attrs = $valueAttr.$selectedAttr.$this->buildAttrs($optionAttrs);
                $optionMarkup .= $this->tag($content, 'option', $optionClass, null, $attrs);
            }
        }
        
        return $optionMarkup;
    }
    
    /**
     * Build a module using the YUI common module format.
     * 
     * @param string The header content.
     * @param string The body content.
     * @param string The footer content.
     * @param string An additional class to add to the module.
     * @param string Additional attributes.
     * @return string HTML.
    **/
    public function module($head, $body, $foot = '', $class = '', $attrs = '')
    {
        $hd = $this->div($head, 'hd');
        $bd = $this->div($body, 'bd');
        $ft = $this->div($foot, 'ft');
        return $this->div($hd.$bd.$ft, 'mod'.($class ? ' '.$class : ''), null, $attrs);
    }
}

