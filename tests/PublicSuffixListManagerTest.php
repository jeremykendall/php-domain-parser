<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp\Cache\FileCacheAdapter;
use Pdp\Http\CurlHttpAdapter;
use Pdp\PublicSuffixListManager;
use PHPUnit\Framework\TestCase;

class PublicSuffixListManagerTest extends TestCase
{
    /**
     * @var PublicSuffixListManager List manager
     */
    protected $manager;

    protected $cachePool;

    protected function setUp()
    {
        $this->cachePool = new FileCacheAdapter('/tmp/test-my-cache');

        $this->manager = new PublicSuffixListManager(
            $this->cachePool,
            new CurlHttpAdapter(),
            'https://publicsuffix.org/list/public_suffix_list.dat'
        );
    }

    protected function tearDown()
    {
        $this->manager = null;
        $this->cachePool = null;
    }

    public function testGetProvidedListFromDefaultCacheDir()
    {
        $publicSuffixList = $this->manager->getList();
        $this->assertGreaterThanOrEqual(300, count($publicSuffixList->getRules()));
    }

    public function testGetDifferentPublicList()
    {
        $publicList = $this->manager->getList();
        $invalidList = $this->manager->getList('invalid type');
        $this->assertEquals($publicList, $invalidList);
    }

    public function testRefreshList()
    {
        $previous = $this->manager->getList();
        $this->assertTrue($this->manager->refreshPublicSuffixList());
        $this->assertEquals($previous, $this->manager->getList());
    }

    public function testGetListRebuildListFromLocalCache()
    {
        $previous = $this->manager->getList(PublicSuffixListManager::ICANN_DOMAINS);
        $this->cachePool->delete(PublicSuffixListManager::ICANN_DOMAINS); //delete local copy of ICAN DOMAINS RULES
        $list = $this->manager->getList(PublicSuffixListManager::ICANN_DOMAINS);
        $this->assertEquals($previous, $this->manager->getList(PublicSuffixListManager::ICANN_DOMAINS));
    }

    public function testGetListRebuildListFromRemoveSource()
    {
        $previous = $this->manager->getList(PublicSuffixListManager::ICANN_DOMAINS);
        $this->cachePool->clear(); //delete all local cache
        $list = $this->manager->getList(PublicSuffixListManager::ICANN_DOMAINS);
        $this->assertEquals($previous, $this->manager->getList(PublicSuffixListManager::ICANN_DOMAINS));
    }
}
