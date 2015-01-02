<?php

namespace Pdp\Uri\Url;

class HostTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider hostDataProvider
     */
    public function test__toString($publicSuffix, $registerableDomain, $subdomain, $hostPart)
    {
        $host = new Host(
            $subdomain,
            $registerableDomain,
            $publicSuffix,
            $hostPart
        );

        $this->assertEquals($hostPart, $host->__toString());
    }

    public function test__toStringWhenHostPartIsNull()
    {
        $host = new Host(
            'www',
            'example.com',
            'com'
        );

        $this->assertEquals('www.example.com', $host->__toString());
    }

    /**
     * @dataProvider hostDataProvider
     */
    public function test__get($publicSuffix, $registerableDomain, $subdomain, $hostPart)
    {
        $parts = array(
            'subdomain' => $subdomain,
            'registerableDomain' => $registerableDomain,
            'publicSuffix' => $publicSuffix,
            'host' => $hostPart,
        );

        $host = new Host(
            $parts['subdomain'],
            $parts['registerableDomain'],
            $parts['publicSuffix'],
            $parts['host']
        );

        $this->assertSame($hostPart, $host->host);
        $this->assertSame($parts['subdomain'], $host->subdomain);
        $this->assertEquals($parts['registerableDomain'], $host->registerableDomain);
        $this->assertEquals($parts['publicSuffix'], $host->publicSuffix);
    }

    /**
     * @dataProvider hostDataProvider
     */
    public function testToArray($publicSuffix, $registerableDomain, $subdomain, $hostPart)
    {
        $parts = array(
            'subdomain' => $subdomain,
            'registerableDomain' => $registerableDomain,
            'publicSuffix' => $publicSuffix,
            'host' => $hostPart,
        );

        $host = new Host(
            $parts['subdomain'],
            $parts['registerableDomain'],
            $parts['publicSuffix'],
            $parts['host']
        );

        $this->assertEquals($parts, $host->toArray());
    }

    public function hostDataProvider()
    {
        // $publicSuffix, $registerableDomain, $subdomain, $hostPart
        return array(
            array('com.au', 'waxaudio.com.au', 'www', 'www.waxaudio.com.au'),
            array('com', 'example.com', null, 'example.com'),
            array('com', 'cnn.com', 'edition', 'edition.cnn.com'),
            array('org', 'wikipedia.org', 'en', 'en.wikipedia.org'),
            array('uk.com', 'example.uk.com', 'a.b', 'a.b.example.uk.com'),
            array(null, null, null, 'localhost'),
        );
    }
}
