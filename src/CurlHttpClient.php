<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

use Throwable;
use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_init;
use function curl_setopt_array;
use const CURLE_OK;
use const CURLOPT_FAILONERROR;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HTTPGET;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;

final class CurlHttpClient implements HttpClient
{
    /**
     * @var array
     */
    private $options;

    /**
     * new instance.
     *
     * @param array $options additional cURL options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPGET => true,
        ];

        try {
            $curl = curl_init();
            $res = @curl_setopt_array($curl, $this->options);
        } catch (Throwable $exception) {
            throw new Exception('Please verify your curl additionnal options', $exception->getCode(), $exception);
        }

        curl_close($curl);
        if (!$res) {
            throw new Exception('Please verify your curl additionnal options');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(string $url): string
    {
        /** @var resource $curl */
        $curl = curl_init($url);
        curl_setopt_array($curl, $this->options);
        /** @var string $content */
        $content = curl_exec($curl);
        $error_code = curl_errno($curl);
        $error_message = curl_error($curl);
        curl_close($curl);
        if (CURLE_OK === $error_code) {
            return $content;
        }

        throw new HttpClientException($error_message, $error_code);
    }
}
