<?php
/**
 * Public Suffix List PHP: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/publicsuffixlist-php for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/publicsuffixlist-php/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp\HttpAdapter;

/**
 * Interface for http adapters.
 *
 * Lifted pretty much completely from William Durand's excellent Geocoder
 * project
 *
 * @link https://github.com/willdurand/Geocoder Geocoder on GitHub
 *
 * @author William Durand <william.durand1@gmail.com>
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 */
interface HttpAdapterInterface
{
    /**
     * Returns the content fetched from a given URL.
     *
     * @param string $url
     *
     * @return string Retrieved content
     */
    public function getContent($url);
}
