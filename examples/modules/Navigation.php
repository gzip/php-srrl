<?php

class Navigation extends SimpleModule
{
    function render($data)
    {
     return <<< NAV
<div id="header" class="robots-noindex">
    <a href="/"><div id="home"><h2>Simple Request Response Layer</h2></div></a>
    <div id="nav"><strong>
        <a title="Documentation" href="/docs/">Docs</a>
        <a title="Examples" href="/examples/">Examples</a>
    </strong></div>
</div>
NAV;
    }
}

