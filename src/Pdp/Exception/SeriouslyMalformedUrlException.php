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

namespace Pdp\Exception;

/**
 * Should be thrown when pdp_parse_url() return false.
 *
 * Exception name based on the PHP documentation: "On seriously malformed URLs, 
 * parse_url() may return FALSE."
 *
 * @see http://php.net/parse_url
 */
class SeriouslyMalformedUrlException extends \InvalidArgumentException implements PdpException
{
    /**
     * Public constructor.
     *
     * @param string     $malformedUrl URL that caused pdp_parse_url() to return false
     * @param int        $code         The Exception code
     * @param \Exception $previous     The previous exception used for the exception chaining
     */
    public function __construct($malformedUrl = '', $code = 0, $previous = null)
    {
        $message = sprintf('"%s" is one seriously malformed url.', $malformedUrl);
        parent::__construct($message, $code, $previous);
    }
}
