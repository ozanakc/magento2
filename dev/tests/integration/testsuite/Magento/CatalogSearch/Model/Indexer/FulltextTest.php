<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogSearch\Model\Indexer;

use Magento\Catalog\Model\Product;
use Magento\CatalogSearch\Model\Resource\Fulltext\Collection;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @magentoDbIsolation disabled
 * @magentoDataFixture Magento/CatalogSearch/_files/indexer_fulltext.php
 */
class FulltextTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Indexer\Model\IndexerInterface
     */
    protected $indexer;

    /**
     * @var \Magento\CatalogSearch\Model\Resource\Engine
     */
    protected $engine;

    /**
     * @var \Magento\CatalogSearch\Model\Resource\Fulltext
     */
    protected $resourceFulltext;

    /**
     * @var \Magento\CatalogSearch\Model\Fulltext
     */
    protected $fulltext;

    /**
     * @var \Magento\Search\Model\QueryFactory
     */
    protected $queryFactory;

    /**
     * @var Product
     */
    protected $productApple;

    /**
     * @var Product
     */
    protected $productBanana;

    /**
     * @var Product
     */
    protected $productOrange;

    /**
     * @var Product
     */
    protected $productPapaya;

    /**
     * @var Product
     */
    protected $productCherry;

    /**
     * @var  \Magento\Framework\Search\Request\Dimension
     */
    protected $dimension;

    protected function setUp()
    {
        /** @var \Magento\Indexer\Model\IndexerInterface indexer */
        $this->indexer = Bootstrap::getObjectManager()->create(
            'Magento\Indexer\Model\Indexer'
        );
        $this->indexer->load('catalogsearch_fulltext');

        $this->engine = Bootstrap::getObjectManager()->get(
            'Magento\CatalogSearch\Model\Resource\Engine'
        );

        $this->resourceFulltext = Bootstrap::getObjectManager()->get(
            'Magento\CatalogSearch\Model\Resource\Fulltext'
        );

        $this->queryFactory = Bootstrap::getObjectManager()->get(
            'Magento\Search\Model\QueryFactory'
        );

        $this->dimension = Bootstrap::getObjectManager()->create(
            '\Magento\Framework\Search\Request\Dimension',
            ['name' => 'scope', 'value' => '1']
        );

        $this->productApple = $this->getProductBySku('fulltext-1');
        $this->productBanana = $this->getProductBySku('fulltext-2');
        $this->productOrange = $this->getProductBySku('fulltext-3');
        $this->productPapaya = $this->getProductBySku('fulltext-4');
        $this->productCherry = $this->getProductBySku('fulltext-5');
    }

    public function testReindexAll()
    {
        $this->indexer->reindexAll();

        $products = $this->search('Apple');
        $this->assertCount(1, $products);
        $this->isEqual($this->productApple, $products[0]);

        $products = $this->search('Simple Product');
        $this->assertCount(5, $products);
        $this->isEqual($this->productApple, $products[0]);
        $this->isEqual($this->productBanana, $products[1]);
        $this->isEqual($this->productOrange, $products[2]);
        $this->isEqual($this->productPapaya, $products[3]);
        $this->isEqual($this->productCherry, $products[4]);
    }

    /**
     *
     */
    public function testReindexRowAfterEdit()
    {
        $this->indexer->reindexAll();

        $this->productApple->setData('name', 'Simple Product Cucumber');
        $this->productApple->save();

        $products = $this->search('Apple');
        $this->assertCount(0, $products);

        $products = $this->search('Cucumber');
        $this->assertCount(1, $products);
        $this->isEqual($this->productApple, $products[0]);

        $products = $this->search('Simple Product');
        $this->assertCount(5, $products);
        $this->isEqual($this->productApple, $products[0]);
        $this->isEqual($this->productBanana, $products[1]);
        $this->isEqual($this->productOrange, $products[2]);
        $this->isEqual($this->productPapaya, $products[3]);
        $this->isEqual($this->productCherry, $products[4]);
    }

    /**
     *
     */
    public function testReindexRowAfterMassAction()
    {
        $this->indexer->reindexAll();

        $productIds = [
            $this->productApple->getId(),
            $this->productBanana->getId(),
        ];
        $attrData = [
            'name' => 'Simple Product Common',
        ];

        /** @var \Magento\Catalog\Model\Product\Action $action */
        $action = Bootstrap::getObjectManager()->get(
            'Magento\Catalog\Model\Product\Action'
        );
        $action->updateAttributes($productIds, $attrData, 1);

        $products = $this->search('Apple');
        $this->assertCount(0, $products);

        $products = $this->search('Banana');
        $this->assertCount(0, $products);

        $products = $this->search('Unknown');
        $this->assertCount(0, $products);

        $products = $this->search('Common');
        $this->assertCount(2, $products);
        $this->isEqual($this->productApple, $products[0]);
        $this->isEqual($this->productBanana, $products[1]);

        $products = $this->search('Simple Product');
        $this->assertCount(5, $products);
        $this->isEqual($this->productApple, $products[0]);
        $this->isEqual($this->productBanana, $products[1]);
        $this->isEqual($this->productOrange, $products[2]);
        $this->isEqual($this->productPapaya, $products[3]);
        $this->isEqual($this->productCherry, $products[4]);
    }

    /**
     * @magentoAppArea adminhtml
     */
    public function testReindexRowAfterDelete()
    {
        $this->indexer->reindexAll();

        $this->productBanana->delete();

        $products = $this->search('Simple Product');

        $this->assertCount(4, $products);

        $productsData = [
            [
                'expected' => ['id' => $this->productApple->getId()] + $this->productApple->getData(),
                'actual' => ['id' => $products[0]->getId()] + $products[0]->getData(),
            ],
            [
                'expected' => ['id' => $this->productOrange->getId()] + $this->productOrange->getData(),
                'actual' => ['id' => $products[1]->getId()] + $products[1]->getData(),
            ],
            [
                'expected' => ['id' => $this->productPapaya->getId()] + $this->productPapaya->getData(),
                'actual' => ['id' => $products[2]->getId()] + $products[2]->getData(),
            ],
            [
                'expected' => ['id' => $this->productCherry->getId()] + $this->productCherry->getData(),
                'actual' => ['id' => $products[3]->getId()] + $products[3]->getData(),
            ],
        ];

        $this->isEqual($this->productApple, $products[0]);
        $this->isEqual($this->productOrange, $products[1]);
        $this->isEqual($this->productPapaya, $products[2]);
        $this->isEqual($this->productCherry, $products[3]);
    }

    /**
     * Search the text and return result collection
     *
     * @param string $text
     * @return Product[]
     */
    protected function search($text)
    {
        $this->resourceFulltext->resetSearchResults();
        $query = $this->queryFactory->get();
        $query->unsetData()->setQueryText($text)->prepare();
        $products = [];
        $collection = Bootstrap::getObjectManager()->create(Collection::class);
        $collection->addSearchFilter($text);
        foreach ($collection as $product) {
            $products[] = $product;
        }
        return $products;
    }

    /**
     * Return product by SKU
     *
     * @param string $sku
     * @return Product
     */
    protected function getProductBySku($sku)
    {
        /** @var Product $product */
        $product = Bootstrap::getObjectManager()->get(
            'Magento\Catalog\Model\Product'
        );
        return $product->loadByAttribute('sku', $sku);
    }

    /**
     * @param Product $expectedProduct
     * @param Product $actualProduct
     * @return void
     */
    private function isEqual(Product $expectedProduct, Product $actualProduct)
    {
        $this->assertEquals(
            $expectedProduct->getId(),
            $actualProduct->getId(),
            $this->getDebugData($expectedProduct, $actualProduct)
        );
    }

    /**
     * @param Product $expectedProduct
     * @param Product $actualProduct
     * @return string
     */
    private function getDebugData(Product $expectedProduct, Product $actualProduct)
    {
        return json_encode(
            [
                'expected' => ['id' => $expectedProduct->getId()] + $expectedProduct->getData(),
                'actual' => ['id' => $actualProduct->getId()] + $actualProduct->getData(),
            ]
        );
    }
}
