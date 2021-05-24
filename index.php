<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet as Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Xlsx;

class ProductImporter
{
    public $shopifyHeaders = [];
    public $shopifyProducts = [];
    public $magentoHeaders = [];
    public $magentoProducts = [];
    public $magentoCollections = [];
    public $collectionDataHeader = [];
    public $collectionData = [];

    function __construct()
	{
        $this->shopifyHeaders = [
            "ID",
            "Handle",
            "Command",
            "Title",
            "Body HTML",
            "Vendor",
            "Type",
            "Tags",
            "Tags Command",
            "Created At",
            "Updated At",
            "Status",
            "Published",
            "Published At",
            "Published Scope",
            "Template Suffix",
            "Gift Card",
            "URL",
            "Row #",
            "Top Row",
            "Variant Inventory Item ID",
            "Variant ID",
            "Variant Command",
            "Option1 Name",
            "Option1 Value",
            "Option2 Name",
            "Option2 Value",
            "Variant Position",
            "Variant SKU",
            "Variant Barcode",
            "Variant Image",
            "Variant Weight",
            "Variant Weight Unit",
            "Variant Price",
            "Variant Compare At Price",
            "Variant Taxable",
            "Variant Tax Code",
            "Variant Inventory Tracker",
            "Variant Inventory Policy",
            "Variant Fulfillment Service",
            "Variant Requires Shipping",
            "Variant Inventory Qty",
            "Variant Inventory Adjust",
            "Image Src",
            "Image Command",
            "Image Position",
            "Image Width",
            "Image Height",
            "Image Alt Text",
            "Metafield: title_tag [string]"
        ];

        $this->getMagentoProducts();
        $this->getCollections();
    }

    private function getTagsFromUrl($url)
    {
        $tagsArray = explode("/", trim($url));
        $tags = implode(",", $tagsArray);
        return $tags;
    }

    private function getTagsFromCategoryId($categoryIds)
    {
        $tags = [];
        foreach($categoryIds as $categoryId) {
            if(empty($this->collectionData[$categoryId])) {
                continue;
            }

            $tagsArray = explode(",", $this->collectionData[$categoryId]);
            $tags = array_merge($tags, $tagsArray);
        }

        $tags = array_unique($tags);
        return implode(",", $tags);
    }

    public function getMagentoProducts()
    {
        $row = 0;
        $this->magentoProducts = [];
        $this->magentoHeaders = [];

        if (($handle = fopen("csv_files/magento_products.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                if($row == 0) {
                    $this->magentoHeaders = $data;
                } else {
                    $product = [];
                    foreach($data as $key => $value) {
                        $product[$this->magentoHeaders[$key]] = $value;
                    }
                    $this->magentoProducts[] = $product;
                }
                $row++;
            }
            fclose($handle);
        }
    }

    public function getCollections()
    {
        $row = 0;
        $this->collectionDataHeader = [];
        $this->collectionData = [];

        if (($handle = fopen("csv_files/categories.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                if($row == 0) {
                    $this->collectionDataHeader = $data;
                } else {
                    $collection = [];
                    foreach($data as $key => $value) {
                        $collection[$this->collectionDataHeader[$key]] = $value;
                    }

                    if($collection['name'] == "Root Catalog") {
                        $collection["tags"] = "root-catalog";
                    } else if($collection['name'] == "Default Category") {
                        $collection["tags"] = "default-category";
                    } else {
                        $collection["tags"] = $this->getTagsFromUrl($collection["url"]);
                    }
                    $this->collectionData[$collection["id"]] = $collection["tags"];
                }
                $row++;
            }
            fclose($handle);
        }
    }

    public function generateShopifyProducts()
    {
        $this->shopifyProducts = [];
        $previousProduct = "";

        foreach($this->magentoProducts as $magentoProduct) {
            $shopifyProduct = [];

            $productTags = $magentoProduct["category_ids"];
            $productImage = trim($magentoProduct['image']);
            $productStatus = strtolower(trim($magentoProduct['status'])) == "enabled" ? 'Active' : 'Draft';
            $productTaxable = strtolower(trim($magentoProduct['tax_class_id'])) == "taxable goods" ? TRUE : FALSE;
            $isTopRow = ($previousProduct == $magentoProduct['name']) ? TRUE : FALSE;

            $colorOption = trim($magentoProduct['color']);
            $sizeOption = trim($magentoProduct['size']);

            if(!empty($productImage)) {
                $productImage = "https://bobmarriottsflyfishingstore.com/media/catalog/product" . $productImage;
            }

            if(!empty($productTags)) {
                $productTags = $this->getTagsFromCategoryId(explode(",", $productTags));
            }

            if(!empty($magentoProduct)) {
                $shopifyProduct["ID"] = "";
                $shopifyProduct["Handle"] = $magentoProduct['url_key'];
                $shopifyProduct["Command"] = "MERGE";
                $shopifyProduct["Title"] = $magentoProduct['name'];
                $shopifyProduct["Body HTML"] = $magentoProduct['description'];
                $shopifyProduct["Vendor"] = $magentoProduct["manufacturer"];
                $shopifyProduct["Type"] = "";
                $shopifyProduct["Tags"] = $productTags;
                $shopifyProduct["Tags Command"] = "REPLACE";
                $shopifyProduct["Created At"] = "";
                $shopifyProduct["Updated At"] = "";
                $shopifyProduct["Status"] = "Active";
                $shopifyProduct["Published"] = "TRUE";
                $shopifyProduct["Published At"] = "";
                $shopifyProduct["Published Scope"] = "web";
                $shopifyProduct["Template Suffix"] = "";
                $shopifyProduct["Gift Card"] = "FALSE";
                $shopifyProduct["URL"] = "";
                $shopifyProduct["Row #"] = "";
                $shopifyProduct["Top Row"] = $isTopRow;
                $shopifyProduct["Variant Inventory Item ID"] = "";
                $shopifyProduct["Variant ID"] = "";
                $shopifyProduct["Variant Command"] = "MERGE";
                $shopifyProduct["Option1 Name"] = !empty($colorOption) ? "Color" : (!empty($sizeOption) ? "Size": "");
                $shopifyProduct["Option1 Value"] = !empty($colorOption) ? $colorOption : (!empty($sizeOption) ? $sizeOption : "");
                $shopifyProduct["Option2 Name"] = (!empty($colorOption) && !empty($sizeOption))  ? "Size" : "";
                $shopifyProduct["Option2 Value"] = (!empty($colorOption) && !empty($sizeOption)) ? $sizeOption : "";
                $shopifyProduct["Variant Position"] = "";
                $shopifyProduct["Variant SKU"] = $magentoProduct["sku"];
                $shopifyProduct["Variant Barcode"] = "";
                $shopifyProduct["Variant Image"] = !$isTopRow ? $productImage : "";
                $shopifyProduct["Variant Weight"] = $magentoProduct["weight"];
                $shopifyProduct["Variant Weight Unit"] = "lb";
                $shopifyProduct["Variant Price"] = $magentoProduct["price"];
                $shopifyProduct["Variant Compare At Price"] = "";
                $shopifyProduct["Variant Taxable"] = $productTaxable;
                $shopifyProduct["Variant Tax Code"] = "";
                $shopifyProduct["Variant Inventory Tracker"] = "shopify";
                $shopifyProduct["Variant Inventory Policy"] = "deny";
                $shopifyProduct["Variant Fulfillment Service"] = "manual";
                $shopifyProduct["Variant Requires Shipping"] = "";
                $shopifyProduct["Variant Inventory Qty"] = $magentoProduct["qty"];
                $shopifyProduct["Variant Inventory Adjust"] = "";
                $shopifyProduct["Image Src"] = $isTopRow ? $productImage : "";
                $shopifyProduct["Image Command"] = "";
                $shopifyProduct["Image Position"] = "";
                $shopifyProduct["Image Width"] = "";
                $shopifyProduct["Image Height"] = "";
                $shopifyProduct["Image Alt Text"] = "";
                $shopifyProduct["Metafield: title_tag [string]"] = $magentoProduct['meta_title'];
            
                $this->shopifyProducts[] = $shopifyProduct;
                $previousProduct = $magentoProduct["name"];
            }
        }
    }

    public function exportShopifyProducts()
    {
        $columnIndex = 1;
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');

        foreach($this->shopifyHeaders as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $header);
            $columnIndex++;
        }

        $rowIndex = 2;
        foreach ($this->shopifyProducts as $product)
        {
            $columnIndex = 1;
            foreach($product as $item) {
                $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $item);
                $columnIndex++;
            }

            $rowIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save('exports/shopify_products_' . time() . '.xlsx');
    }
}

$productImporter = new ProductImporter();
$productImporter->generateShopifyProducts();
$productImporter->exportShopifyProducts();
?>
