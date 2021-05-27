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
    public $collectionDataById = [];
    public $collectionDataByUrl = [];

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

        $this->getCollections();
        $this->getMagentoProducts();
    }

    private function getTagsFromUrl($url)
    {
        $tagsArray = explode("/", trim($url));
        $tags = implode(",", $tagsArray);
        return $tags;
    }

    /* Get tag and product type from category ID list */
    private function getTagsType($categoryIds)
    {
        $data = array(
            "tags" => [],
            "type" => "",
        );

        $alternativeTypes = [];

        foreach($categoryIds as $categoryId) {
            $tagsTypeData = $this->getTagsTypeFromCategoryId($categoryId);
            $data["tags"] = array_merge($data["tags"], $tagsTypeData["tags"]);
            $data["type"] = $tagsTypeData["type"];
            $alternativeTypes[] = $tagsTypeData["alternative_type"];
        }

        if(empty($data["type"])) {
            foreach($alternativeTypes as $type) {
                if(!empty($type)) {
                    $data["type"] = $type;
                    break;
                }
            }
        }

        $data["tags"] = array_unique($data["tags"]);
        $data["tags"] = implode(",", $data["tags"]);
        return $data;
    }

    /* Get tag and product type from category ID */
    private function getTagsTypeFromCategoryId($categoryId)
    {
        $data = array(
            "tags" => [],
            "type" => "",
            "alternative_type" => ""
        );

        $tagPrefixes = array(
            "landing:",
            "category:",
            "subcategory:",
            "group:",
            "material_type:"
        );

        if(empty($this->collectionDataById[$categoryId])) {
            return $data;
        }

        $breadcrumbs = explode("/", $this->collectionDataById[$categoryId]);
        
        foreach($breadcrumbs as $key => $breadcrumb) {
            $subBreadcrumbs = array_slice($breadcrumbs, 0, ($key + 1));
            $subUrl = implode("/", $subBreadcrumbs);
            
            if(!empty($this->collectionDataByUrl[$subUrl])) {
                $data["tags"][] = $tagPrefixes[$key] . $this->collectionDataByUrl[$subUrl];

                if($key == 1) {
                    $data["alternative_type"] = $this->collectionDataByUrl[$subUrl];
                }

                if($key == 2) {
                    $data["type"] = $this->collectionDataByUrl[$subUrl];
                }
            }
        }
        
        return $data;
    }

    /* Remove font tags and inline styles from string */
    private function removeInlineStyles($html)
    {
        $tags_to_strip = Array("font", "FONT");
        $filteredHtml = $html;

        $filteredHtml = preg_replace( '/\s*style\s*=\s*[\'][^\']*[\']/', '', stripslashes( $filteredHtml ) );
        $filteredHtml = preg_replace( '/\s*style\s*=\s*[\"][^\"]*[\"]/', '', stripslashes( $filteredHtml ) );
        $filteredHtml = preg_replace( '/\s*<\s*font[^<]*>/', '', stripslashes( $filteredHtml ) );
        $filteredHtml = preg_replace( '/\s*<\/\s*font[^<]*>/', '', stripslashes( $filteredHtml ) );
        $filteredHtml = preg_replace( '/\s*<\s*FONT[^<]*>/', '', stripslashes( $filteredHtml ) );
        $filteredHtml = preg_replace( '/\s*<\/\s*FONT[^<]*>/', '', stripslashes( $filteredHtml ) );

        return $filteredHtml;
    }

    /* Get magento products from csv */
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
                    if(trim($product["status"]) != "Disabled") {
                        $this->magentoProducts[] = $product;
                    }
                }
                $row++;
            }
            fclose($handle);
        }
    }

    /* Get collection data from csv */
    public function getCollections()
    {
        $row = 0;
        $this->collectionDataHeader = [];
        $this->collectionDataById = [];
        $this->collectionDataByUrl = [];

        if (($handle = fopen("csv_files/categories.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                if($row == 0) {
                    $this->collectionDataHeader = $data;
                } else {
                    $collection = [];
                    foreach($data as $key => $value) {
                        $collection[$this->collectionDataHeader[$key]] = $value;
                    }

                    $this->collectionDataById[$collection["id"]] = $collection["url"];
                    $this->collectionDataByUrl[$collection["url"]] = $collection["name"];
                }
                $row++;
            }
            fclose($handle);
        }
    }

    /* Generate shopify products array from magento products */
    public function generateShopifyProducts()
    {
        $this->shopifyProducts = [];
        $previousProduct = "";
        $previousProductSKU = "";
        $currentSheetIndex = -1;
        $currentRootProductIndex = -1;

        foreach($this->magentoProducts as $magentoProduct) {
            $productTagsType = array(
                "tags" => "",
                "type" => ""
            );
            if(!empty($magentoProduct)) {
                $productStatus = trim($magentoProduct['status']);

                /* Multiple Images */
                if(empty($productStatus) || $previousProductSKU == $magentoProduct["sku"]) {
                    $shopifyProduct = $this->shopifyProducts[$currentSheetIndex];

                    if($shopifyProduct["Top Row"] == "TRUE") {
                        $productImages = explode(";", $shopifyProduct["Image Src"]);
                    } else {
                        $productImages = explode(";", $shopifyProduct["Variant Image"]);
                    }
                    
                    if(!empty(trim($magentoProduct['image']))) {
                        $productImages[] = trim($magentoProduct['image']);
                    }

                    if($shopifyProduct["Top Row"] == "TRUE") {
                        $this->shopifyProducts[$currentSheetIndex]["Image Src"] = implode(";", $productImages);
                    } else {
                        $this->shopifyProducts[$currentSheetIndex]["Variant Image"] = implode(";", $productImages);
                    }

                    continue;
                }

                $productImage = trim($magentoProduct['image']);
                $productTaxable = strtolower(trim($magentoProduct['tax_class_id'])) == "taxable goods" ? TRUE : FALSE;
                $productPrice = trim($magentoProduct['price']);
                $productSpecialPrice = trim($magentoProduct['special_price']);

                $isTopRow = ($previousProduct == $magentoProduct['name']) ? FALSE : TRUE;

                /* Add Sale Tag if there is special price */
                if(!empty($productSpecialPrice) && $currentRootProductIndex >= 0) {
                    $shopifyProduct = $this->shopifyProducts[$currentRootProductIndex];
                    if(empty($shopifyProduct["Tags"])) {
                        $this->shopifyProducts[$currentRootProductIndex]["Tags"] = "OnSale";
                    } else {
                        $productTags = explode(",", $shopifyProduct["Tags"]);
                        $productTags[] = "OnSale";
                        $productTags = array_unique($productTags);
                        $this->shopifyProducts[$currentRootProductIndex]["Tags"] = implode(",", $productTags);
                    }
                }

                $colorOption = trim($magentoProduct['color']);
                $sizeOption = trim($magentoProduct['size']);

                if(!empty($productImage)) {
                    $productImage = "https://bobmarriottsflyfishingstore.com/media/catalog/product" . $productImage;
                }

                if(!empty(trim($magentoProduct["category_ids"]))) {
                    $productTagsType = $this->getTagsType(explode(",", trim($magentoProduct["category_ids"])));
                }
                
                $shopifyProduct = [];

                $shopifyProduct["ID"] = "";
                $shopifyProduct["Handle"] = $isTopRow ? $magentoProduct['url_key'] : "";
                $shopifyProduct["Command"] = "MERGE";
                $shopifyProduct["Title"] = $magentoProduct['name'];
                $shopifyProduct["Body HTML"] = $this->removeInlineStyles($magentoProduct['description']);
                $shopifyProduct["Vendor"] = $magentoProduct["manufacturer"];
                $shopifyProduct["Type"] = $productTagsType["type"];
                $shopifyProduct["Tags"] = $isTopRow ? $productTagsType["tags"] : "";
                $shopifyProduct["Tags Command"] = $isTopRow ? "REPLACE" : "";
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
                $shopifyProduct["Top Row"] = $isTopRow ? "TRUE" : "FALSE";
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
                $shopifyProduct["Variant Price"] = !empty($productSpecialPrice) ? $productSpecialPrice : $productPrice;
                $shopifyProduct["Variant Compare At Price"] = !empty($productSpecialPrice) ? $productPrice : "";
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
                $currentSheetIndex++;
                if($isTopRow) {
                    $currentRootProductIndex = $currentSheetIndex;
                }
                $previousProduct = $magentoProduct["name"];
                $previousProductSKU = $magentoProduct["sku"];
            }
        }
    }

    /* Generate excel file for products import using Matrixify */
    public function exportShopifyProducts($limit = FALSE)
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
        $productIndex = 0;
        foreach ($this->shopifyProducts as $product)
        {
            $columnIndex = 1;

            if($product["Top Row"]) {
                $productIndex++;
            }

            if($limit !== FALSE && $productIndex >= $limit) {
                break;
            }

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
$productImporter->exportShopifyProducts(500);
?>


<!doctype html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Magento Importer</title>
    <meta name="author" content="Alex">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
</head>

<body>
    <div class="mt-5 px-5">
        <?php if (!empty($productImporter->shopifyProducts)): ?>

        <table class="table table-bordered">
            <thead>
                <th scope="col">#</th>
                <?php foreach ($productImporter->shopifyHeaders as $head): ?>
                    <th scope="col"><?php echo $head; ?></th>
                <?php endforeach; ?>
            </thead>
            <tbody>
                <?php $key = 1; ?>
                <?php foreach ($productImporter->shopifyProducts as $product): ?>
                    <?php if($key >= 1000): ?>
                    <?php break; ?>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><?php echo $key ++; ?></td>
                        <?php foreach ($product as $product_key => $item): ?>
                            <td><?php if ($product_key == "Body HTML"): ?><code><?php endif; ?><?php echo $item; ?><?php if ($product_key == "Body HTML"): ?></code><?php endif; ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </div>
    
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>
</html>