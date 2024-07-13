<?php 
namespace Jaui\Urlwithsku\Model;

/**
 * Class AbstractAction
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
*/

class ProductUrlPathGenerator extends \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator {

    protected function prepareProductDefaultUrlKey(\Magento\Catalog\Model\Product $product)
    {
        $storedProduct = $this->productRepository->getById($product->getId());
        $storedUrlKey = $storedProduct->getUrlKey();
        return $storedUrlKey ?: $product->formatUrlKey($storedProduct->getName() . "-" . $storedProduct->getSku());
    }



    protected function prepareProductUrlKey(\Magento\Catalog\Model\Product $product)
    {
        $urlKey = $product->getUrlKey();
        return $product->formatUrlKey($urlKey === '' || $urlKey === null ? $product->getName() . "-" . $product->getSku() : $urlKey);

    }
}