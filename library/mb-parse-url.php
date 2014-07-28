<?php

namespace {
    if (!function_exists('mb_parse_url')) {
        /**
         * UTF-8 aware parse_url() replacement.
         *
         * Taken from php.net manual comments {@link http://php.net/manual/en/function.parse-url.php#114817}
         *
         * @param  string  $url       The URL to parse
         * @param  integer $component Specify one of PHP_URL_SCHEME, PHP_URL_HOST,
         *                            PHP_URL_PORT, PHP_URL_USER, PHP_URL_PASS, PHP_URL_PATH, PHP_URL_QUERY or
         *                            PHP_URL_FRAGMENT to retrieve just a specific URL component as a string
         *                            (except when PHP_URL_PORT is given, in which case the return value will
         *                            be an integer).
         * @return mixed   See parse_url documentation {@link http://us1.php.net/parse_url}
         */
        function mb_parse_url($url, $component = -1)
        {
            $enc_url = preg_replace_callback(
                '%[^:/@?&=#\[\]]+%usD',
                function ($matches) {
                    return urlencode($matches[0]);
                },
                    $url
                );

            $parts = parse_url($enc_url, $component);

            if ($parts === false) {
                return $parts;
            }

            if (is_array($parts)) {
                foreach ($parts as $name => $value) {
                    $parts[$name] = urldecode($value);
                }
            } else {
                $parts = urldecode($parts);
            }

            return $parts;
        }
    }
}
