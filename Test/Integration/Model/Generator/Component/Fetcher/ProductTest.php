<?php

namespace Doofinder\Feed\Test\Integration\Model\Generator\Component\Fetcher;

/**
 * Test class for \Doofinder\Feed\Model\Generator\Component\Fetcher\Product
 *
 * @magentoDataFixture Magento/Catalog/_files/multiple_products.php
 */
class ProductTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doofinder\Feed\Model\Generator\Component\Fetcher\Product
     */
    private $_model;

    public function setUp()
    {
        $this->_model = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            '\Doofinder\Feed\Model\Generator\Component\Fetcher\Product'
        );
    }

    /**
     * Test fetch() method
     */
    public function testFetch()
    {
        $items = $this->_model->fetch();

        $this->assertEquals(3, count($items));
        $this->assertEquals(
            [
                'simple1',
                'simple2',
                'simple3',
            ],
            array_map(function ($item) {
                return $item->getContext()->getSku();
            }, $items)
        );

        $this->assertEquals(true, $this->_model->isStarted());
        $this->assertEquals(true, $this->_model->isDone());
    }

    /**
     * Test fetch() method with pagination
     *
     * @dataProvider testFetchWithPaginationProvider
     */
    public function testFetchWithPagination($page, $sku, $isStarted, $isDone)
    {
        $this->_model->setCurPage($page);
        $this->_model->setPageSize(1);

        $items = $this->_model->fetch();

        $this->assertEquals(1, count($items));
        $this->assertEquals($sku, $items[0]->getContext()->getSku());

        $this->assertEquals($isStarted, $this->_model->isStarted());
        $this->assertEquals($isDone, $this->_model->isDone());
    }

    public function testFetchWithPaginationProvider()
    {
        return [
            [1, 'simple1', true, false],
            [2, 'simple2', false, false],
            [3, 'simple3', false, true],
        ];
    }

    /**
     * Test fetch() method with multiple websites
     *
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoDataFixture Magento/Store/_files/website.php
     * @magentoDataFixture Magento/Catalog/_files/multiple_products.php
     */
    public function testFetchMultiwebsite()
    {
        /** @var \Magento\Store\Api\WebsiteRepositoryInterface $websiteRepositoryFactory */
        $websiteRepositoryFactory = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            '\Magento\Store\Api\WebsiteRepositoryInterface'
        );

        $website = $websiteRepositoryFactory->get('test');

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryFactory */
        $productRepositoryFactory = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            '\Magento\Catalog\Api\ProductRepositoryInterface'
        );

        $product = $productRepositoryFactory->get('simple1');
        $product->setWebsiteIds([$website->getId()])
            ->save();

        $items = $this->_model->fetch();

        $this->assertEquals(2, count($items));
        $this->assertEquals(
            [
                'simple2',
                'simple3',
            ],
            array_map(function ($item) {
                return $item->getContext()->getSku();
            }, $items)
        );

        $this->assertEquals(true, $this->_model->isStarted());
        $this->assertEquals(true, $this->_model->isDone());
    }
}
