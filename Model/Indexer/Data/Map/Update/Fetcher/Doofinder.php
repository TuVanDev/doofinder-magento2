<?php

declare(strict_types=1);

namespace Doofinder\Feed\Model\Indexer\Data\Map\Update\Fetcher;

use Doofinder\Feed\Api\Data\FetcherInterface;
use Doofinder\Feed\Api\Data\Generator\MapInterface;
use Doofinder\Feed\Model\Config\Indexer\Attributes;
use Doofinder\Feed\Model\Generator\Product as ProductGenerator;
use Magento\Store\Model\App\Emulation;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\App\Area;

class Doofinder implements FetcherInterface
{
        /**
     * @var Emulation
     */
    protected $appEmulation;

    /**
     * @var array|null
     */
    private $processed;

    /**
     * @var ProductGenerator
     */
    private $productGenerator;

    /**
     * @var ProductCollectionFactory
     */
    private $productColFactory;

    /**
     * @var Attributes
     */
    private $attributes;

    /**
     * @var ObjectManagerInterface
     */
    private $_objectManager;

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * Doofinder constructor.
     *
     * @param ProductCollectionFactory $collectionFactory
     * @param Attributes $attributes
     * @param ObjectManagerInterface $objectmanager
     * @param Manager $moduleManager
     * @param ProductGenerator $productGenerator,
     * @param Emulation $appEmulation
     */
    public function __construct(
        ProductCollectionFactory $collectionFactory,
        Attributes $attributes,
        ObjectManagerInterface $objectmanager,
        Manager $moduleManager,
        ProductGenerator $productGenerator,
        Emulation $appEmulation
    ) {
        $this->productColFactory = $collectionFactory;
        $this->attributes = $attributes;
        $this->productGenerator = $productGenerator;
        $this->_objectManager = $objectmanager;
        $this->moduleManager = $moduleManager;
        $this->appEmulation = $appEmulation;
    }

    /**
     * @inheritDoc
     */
    public function process(array $documents, int $storeId)
    {
        $this->clear();
        $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        $productIds = array_keys($documents);
        $productCollection = $this->getProductCollection($productIds, $storeId);
        $fields = $this->getFields($storeId);
        foreach ($productCollection as $product) {
            $productId = $product->getId();
            $type = strtolower($product->getTypeId());
            $this->processed[$productId] = [];
            foreach ($fields as $indexField => $attribute) {
                $this->processed[$productId][$indexField] = $this->productGenerator->get($product, $attribute);
            }
            $this->processed[$productId] = array_filter($this->processed[$productId]);
        }
        $this->appEmulation->stopEnvironmentEmulation();
    }

    /**
     * @inheritDoc
     */
    public function get(int $productId): array
    {
        return $this->processed[$productId] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        $this->processed = [];
    }

    /**
     * Get product generator
     *
     * @param string $type
     * @return MapInterface
     */
    private function getGenerator(string $type): MapInterface
    {
        return $this->generators[$type] ?? $this->generators['simple'];
    }

    /**
     * Get Doofinder fields configured in specific store view
     * @param integer $storeId
     * @return array
     */
    private function getFields(int $storeId): array
    {
        return $this->attributes->get($storeId);
    }

    /**
     * @param array $productIds
     * @param integer $storeId
     * @param int|null $stockId
     *
     * @return ProductCollection
     */
    private function getProductCollection(array $productIds, int $storeId): ProductCollection
    {
        $collection = $this->productColFactory
            ->create()
            ->addIdFilter($productIds)
            ->addAttributeToSelect('*')
            ->addStoreFilter($storeId)
            ->addAttributeToSort('id', 'asc');
        /**
         * @notice Magento 2.2.x included a default stock filter
         *         so that 'out of stock' products are excluded by default.
         *         We override this behavior here.
         */
        $collection->setFlag('has_stock_status_filter', true);

        if ($this->moduleManager->isEnabled('Magento_InventoryCatalogApi')) {
            $this->updateDataCollectionWithMSI($collection);
        }else {
            $this->updateDataCollectionWithoutMSI($collection);
        }

        return $collection;
    }

    /**
     * Function to update the collection to include out of stock products when the user has MSI enabled
     * 
     * @param ProductCollection $collection
     */
    private function updateDataCollectionWithMSI(&$collection) {
        $defaultStockProvider = $this->_objectManager->create(\Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface::class);
        $addStockDataToCollection = $this->_objectManager->create(\Magento\InventoryCatalog\Model\ResourceModel\AddStockDataToCollection::class);

        $stockId = $stockId ?? $defaultStockProvider->getId();
        $addStockDataToCollection->execute($collection, false, $stockId);
    }

    /**
     * Function to update the collection to include out of stock products when the user has MSI disabled
     * 
     * @param ProductCollection $collection
     */
    private function updateDataCollectionWithoutMSI(&$collection) {
        $stockStatusResource = $this->_objectManager->create(\Magento\CatalogInventory\Model\ResourceModel\Stock\Status::class);
        $stockStatusResource->addStockDataToCollection($collection, false);
    }
}
