<?php

class SimpleStringTest extends PHPUnit_Framework_TestCase
{
    public function testRenderTemplate()
    {
        $this->assertEquals('<b>FOO</b>', SimpleString::renderTemplate('<b>{{foo}}</b>', array('foo'=>'FOO')));
    }

    public function testBuildParams()
    {
        $params = array('foo'=>'F OO', 'bar'=>'B&R');
        $this->assertEquals('?foo=F%20OO&bar=B%26R', SimpleString::buildParams($params, '?'));
        $this->assertEquals('', SimpleString::buildParams(array(), '?'));
        $this->assertEquals(' foo=F OO, bar=B&R', SimpleString::buildParams($params, ' ', ', ', '=', null));
        $this->assertEquals('foo:F OO| bar:B&amp;R', SimpleString::buildParams($params, '', '| ', ':', array(new SimpleString, 'escape')));
    }

    public function testWrap()
    {
        $this->assertEquals('"foo"', SimpleString::wrap('foo', '"', '"'));
        $this->assertEquals('""foo""', SimpleString::wrap('"foo"', '"', '"'));
        $this->assertEquals('"foo"', SimpleString::wrap('"foo"', '"', '"', false));
        $this->assertEquals('', SimpleString::wrap('', '"', '"'));
        $this->assertEquals('äèä', SimpleString::wrap('äèä', 'ä', 'ä', false));
    }

    public function testPrepend()
    {
        $this->assertEquals(':foo', SimpleString::prepend('foo', ':'));
        $this->assertEquals(':foo', SimpleString::prepend(':foo', ':'));
        $this->assertEquals('::foo', SimpleString::prepend(':foo', ':', true));
    }

    public function testAppend()
    {
        $this->assertEquals('foo:', SimpleString::append('foo', ':'));
        $this->assertEquals('foo:', SimpleString::append('foo:', ':'));
        $this->assertEquals('foo::', SimpleString::append('foo:', ':', true));
    }

    public function testReduceWhitespace()
    {
        $this->assertEquals('F O O', SimpleString::reduceWhitespace(" \tF\t\nO  O\r\n"));
    }

    public function testEscape()
    {
        $esc = SimpleString::escape('\'"&');
        $this->assertEquals('&#039;&quot;&amp;', $esc);
        $this->assertEquals('&#039;&quot;&amp;', SimpleString::escape($esc));
        $this->assertEquals('&amp;#039;&amp;quot;&amp;amp;', SimpleString::escape($esc, true));
    }
}
