<?php

namespace Jaui\ProductUpdate\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\File\Csv;
use Magento\Framework\App\State;

class CsvProductUpdater
{
    protected $productRepository;
    protected $productFactory;
    protected $csvProcessor;
    protected $appState;

    public function __construct(ProductRepository $productRepository, ProductFactory $productFactory, Csv $csvProcessor, State $appState)
    {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->csvProcessor = $csvProcessor;
        $this->appState = $appState;
    }

    public function updateProductsFromCsv($csvFilePath)
    {
        // Set the Area Code - Required before updating products
        $this->appState->setAreaCode('frontend');

        $data = $this->csvProcessor->getData($csvFilePath);
        $headers = array_shift($data); // Assuming the first row contains headers

        // echo "<pre>"; print_r($data); echo "</pre>";

        foreach ($data as $rowData) {

        $trimmed_sku = preg_replace('/\s+/', '', $rowData[array_search('sku', $headers)]); // Remove whitespace on SKU, Convert to uppercase
  
            try {
                // If SKU exist, update product
                $product = $this->productRepository->get($trimmed_sku);
                $product->setName($rowData[array_search('name', $headers)]);
                $product->setShortDescription($rowData[array_search('short_description', $headers)]);
		$product->setDescription($rowData[array_search('description', $headers)]);
                $product->setPrice($rowData[array_search('price', $headers)]);
                $product->setMsrp($rowData[array_search('rrp', $headers)]);
                $this->productRepository->save($product);
            
            } catch (\Exception $e) {
                // Else add new product 
                $product_new = $this->productFactory->create();
                $product_new->setWebsiteIds(array(1));
                $product_new->setAttributeSetId(4);
                $product_new->setTypeId('simple');
                $product_new->setCreatedAt(strtotime('now')); 
                $product_new->setSku($trimmed_sku); 
		$product_new->setName($rowData[array_search('name', $headers)]);
                $product_new->setShortDescription($rowData[array_search('short_description', $headers)]);
                $product_new->setDescription($rowData[array_search('description', $headers)]); 
                $product_new->setStatus(1);
                $product_new->setTaxClassId(0); // (0 - none, 1 - default, 2 - taxable, 4 - shipping)
                $product_new->setVisibility(4); // catalog and search visibility
                $product_new->setPrice($rowData[array_search('price', $headers)]);   //DO NOT REMOVE -- REQUIRED FIELD
                $product_new->setMsrp($rowData[array_search('rrp', $headers)]); 
                $product_new->setCost(1);                                            //DO NOT REMOVE -- REQUIRED FIELD
                $product_new->setStockData(
                    array(
                        'use_config_manage_stock' => 1, 
                        'manage_stock' => 1, // manage stock
                        'min_sale_qty' => 1, // Shopping Cart Minimum Qty Allowed 
                        'max_sale_qty' => 2, // Shopping Cart Maximum Qty Allowed
                        'is_in_stock' => 1, // Stock Availability of product
                        'qty' =>  100
                    )
                );
    
                $product_new->save();
            }   
        }
        return true; 
    }
}
