<?php

declare(strict_types=1);

namespace Pdp\Tests;

use org\bovigo\vfs\vfsStream;
use Pdp\Http\CurlHttpAdapter;
use Pdp\PublicSuffixList;
use Pdp\PublicSuffixListManager;
use Pdp\Tests\Cache\FileCacheAdapter;
use PHPUnit\Framework\TestCase;

class PublicSuffixListManagerTest extends TestCase
{
    /**
     * @var PublicSuffixListManager List manager
     */
    protected $manager;
    protected $cachePool;
    protected $cacheDir;
    protected $root;
    protected $sourceUrl = 'https://publicsuffix.org/list/public_suffix_list.dat';

    public function setUp()
    {
        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');
        $this->cachePool = new FileCacheAdapter($this->cacheDir);
        $this->manager = new PublicSuffixListManager($this->cachePool, new CurlHttpAdapter());
    }

    public function tearDown()
    {
        $this->manager = null;
        $this->cachePool = null;
        $this->cacheDir = null;
        $this->root = null;
    }

    public function testGetDifferentPublicList()
    {
        $publicSuffixList = $this->manager->getList(PublicSuffixList::ALL_DOMAINS, $this->sourceUrl);
        $invalidList = $this->manager->getList('invalid type');
        $this->assertEquals($publicSuffixList, $invalidList);
    }

    public function testRefreshList()
    {
        $previous = $this->manager->getList();
        $this->assertTrue($this->manager->refreshPublicSuffixList($this->sourceUrl));
        $this->assertEquals($previous, $this->manager->getList());
    }

    public function testGetListRebuildListFromLocalCache()
    {
        $previous = $this->manager->getList(PublicSuffixList::ICANN_DOMAINS, $this->sourceUrl);
        $this->cachePool->delete(PublicSuffixList::ICANN_DOMAINS, $this->sourceUrl); //delete local copy of ICAN DOMAINS RULES
        $list = $this->manager->getList(PublicSuffixList::ICANN_DOMAINS, $this->sourceUrl);
        $this->assertEquals($previous, $this->manager->getList(PublicSuffixList::ICANN_DOMAINS, $this->sourceUrl));
    }

    public function testGetListRebuildListFromRemoveSource()
    {
        $previous = $this->manager->getList(PublicSuffixList::ICANN_DOMAINS, $this->sourceUrl);
        $this->cachePool->clear(); //delete all local cache
        $list = $this->manager->getList(PublicSuffixList::ICANN_DOMAINS, $this->sourceUrl);
        $this->assertEquals($previous, $this->manager->getList(PublicSuffixList::ICANN_DOMAINS, $this->sourceUrl));
    }
}
