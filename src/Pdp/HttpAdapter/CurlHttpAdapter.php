<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2014 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
namespace Pdp\HttpAdapter;


use Pdp\HttpAdapter\Exception;

/**
 * cURL http adapter.
 *
 * Lifted pretty much completely from William Durand's excellent Geocoder
 * project
 *
 * @link https://github.com/willdurand/Geocoder Geocoder on GitHub
 *
 * @author William Durand <william.durand1@gmail.com>
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 */
class CurlHttpAdapter implements HttpAdapterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getContent($url, $ignoreSslPeer = false)
    {
        echo sprintf("Get content (ignore HTTPS: %s)...\n", $ignoreSslPeer ? 'true' : 'false');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($ignoreSslPeer) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        $content = curl_exec($ch);

        if ($errNo = curl_errno($ch)) {
            throw new Exception\CurlHttpAdapterException('Cannot fetch data from URL: ' . $url, $errNo);
        }
        curl_close($ch);

        return $content;
    }
}
