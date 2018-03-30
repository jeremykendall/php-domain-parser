<?php
/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

/**
 * Append a domain name to another domain name.
 *
 * @param mixed $host
 * @param mixed $subject
 *
 * @return Domain
 */
function append($host, $subject): Domain
{
    if (!$subject instanceof Domain) {
        $subject = new Domain($subject);
    }

    if (!$host instanceof DomainInterface) {
        $host = new Domain($host);
    }

    if (null === $host->getContent()) {
        return $subject;
    }

    static $pattern = '/[^\x20-\x7f]/';
    $dContent = $subject->getContent();
    $host = preg_match($pattern, $dContent) ? $host->toUnicode() : $host->toAscii();
    $domain = new Domain($dContent.'.'.$host->getContent());

    if ($host instanceof PublicSuffix) {
        return $domain->withPublicSuffix($host);
    }

    if ($host instanceof Domain) {
        return $domain->withPublicSuffix(PublicSuffix::createFromDomain($host));
    }

    return $domain;
}

/**
 * Prepend a domain name to another domain name.
 *
 * @param mixed $host
 * @param mixed $subject
 *
 * @return Domain
 */
function prepend($host, $subject): Domain
{
    if (!$subject instanceof Domain) {
        $subject = new Domain($subject);
    }

    if (!$host instanceof DomainInterface) {
        $host = new PublicSuffix($host);
    }

    if (null === $host->getContent()) {
        return $subject;
    }

    $dContent = $subject->getContent();
    static $pattern = '/[^\x20-\x7f]/';
    $host = preg_match($pattern, $dContent) ? $host->toUnicode() : $host->toAscii();

    return new Domain($host->getContent().'.'.$dContent, PublicSuffix::createFromDomain($subject));
}

/**
 * Replace a label from the domain with a domain name
 *
 * @param int   $key
 * @param mixed $label
 * @param mixed $subject
 *
 * @return Domain
 */
function replace(int $key, $label, $subject): Domain
{
    if (!$subject instanceof Domain) {
        $subject = new Domain($subject);
    }

    if (!$label instanceof DomainInterface) {
        $label = new Domain($label);
    }

    if (null === $label->getContent()) {
        return $subject;
    }

    $nb_labels = count($subject);
    $offset = filter_var($key, FILTER_VALIDATE_INT, ['options' => ['min_range' => - $nb_labels, 'max_range' => $nb_labels - 1]]);
    if (false === $offset) {
        throw new Exception(sprintf('no label was found for the given key `%s`', $key));
    }

    if ($offset < 0) {
        $offset = $nb_labels + $offset;
    }

    static $pattern = '/[^\x20-\x7f]/';
    $label = preg_match($pattern, $subject->getContent()) ? $label->toUnicode() : $label->toAscii();
    if ($label->getContent() === $subject->getLabel($offset)) {
        return $subject;
    }

    $labels = iterator_to_array($subject, false);
    $labels[$offset] = $label->getContent();
    $domain = new Domain(implode('.', array_reverse($labels)));

    $subjectPS = PublicSuffix::createFromDomain($subject);
    if ($key >= count($subjectPS)) {
        return $domain->withPublicSuffix($subjectPS);
    }

    if ($key !== 0) {
        return $domain;
    }

    if ($label instanceof PublicSuffix) {
        return $domain->withPublicSuffix($label);
    }

    if ($label instanceof Domain) {
        return $domain->withPublicSuffix(PublicSuffix::createFromDomain($label));
    }

    return $domain->withPublicSuffix(new PublicSuffix($label->getContent()));
}

/**
 * Replace the public suffix for a given Domain name.
 *
 * If the domain has no information regarding its public suffix
 * its last label will be considered as its public suffix label and will
 * be replaced.
 *
 * @param mixed $publicSuffix
 * @param mixed $subject
 *
 * @return Domain
 */
function public_suffix_replace($publicSuffix, $subject): Domain
{
    if (!$subject instanceof Domain) {
        $subject = new Domain($subject);
        $subject = $subject->withPublicSuffix(new PublicSuffix($subject->getLabel(0)));
    }

    if (!$publicSuffix instanceof PublicSuffix) {
        $publicSuffix = new PublicSuffix($publicSuffix);
    }

    if (null === $publicSuffix->getContent()) {
        return $subject->withPublicSuffix($publicSuffix);
    }

    $dContent = $subject->getContent();
    $dPublicSuffix = $subject->getPublicSuffix();

    static $pattern = '/[^\x20-\x7f]/';
    $publicSuffix = preg_match($pattern, $dContent) ? $publicSuffix->toUnicode() : $publicSuffix->toAscii();

    $psContent = $publicSuffix->getContent();
    if ($dPublicSuffix === $psContent) {
        return $subject;
    }

    return new Domain(substr($dContent, 0, - strlen($dPublicSuffix)).$psContent, $publicSuffix);
}
