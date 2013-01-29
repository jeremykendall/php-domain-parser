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
            $publicSuffix
        );

		$this->assertEquals($hostPart, $host->__toString());
	}

	/**
     * @dataProvider hostDataProvider
	 */
    public function test__get($publicSuffix, $registerableDomain, $subdomain, $hostPart)
    {
        $parts = array(
            'subdomain' => $subdomain,
            'registerableDomain' => $registerableDomain,
            'publicSuffix' => $publicSuffix
        );

        $host = new Host(
            $parts['subdomain'],
            $parts['registerableDomain'],
            $parts['publicSuffix']
        );

        $this->assertSame($parts['subdomain'], $host->subdomain);
        $this->assertEquals($parts['registerableDomain'], $host->registerableDomain);
        $this->assertEquals($parts['publicSuffix'], $host->publicSuffix);
    }

    public function hostDataProvider()
    {
        return array(
            array('com.au', 'waxaudio.com.au', 'www', 'www.waxaudio.com.au'),
            array('com', 'example.com', null, 'example.com'),
            array('com', 'cnn.com', 'edition', 'edition.cnn.com'),
            array('org', 'wikipedia.org', 'en', 'en.wikipedia.org'),
            array('uk.com', 'example.uk.com', 'a.b', 'a.b.example.uk.com')
        );
    }
}
