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
            "Metafield: title_tag [string]",
            "Metafield: wholesale_price [string]",
            "Metafield: short_description [string]",
            "Metafield: tax_class_id [string]",
            "Metafield: keyword [string]"
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

    private function checkIfSkuExists($productDetails, $sku)
    {
        $returnData = array(
            "isRoot" => FALSE,
            "variantIndex" => FALSE
        );

        if($productDetails["root"]["Variant SKU"] == $sku) {
            $returnData["isRoot"] = TRUE;
        } else {
            foreach($productDetails["variants"] as $key => $variant) {
                if($variant["Variant SKU"] == $sku) {
                    $returnData["variantIndex"] = $key;
                    break;
                }
            }
        }

        return $returnData;
    }

    /* Generate shopify products array from magento products */
    public function generateShopifyProducts()
    {
        $this->shopifyProducts = [];

        foreach($this->magentoProducts as $magentoProduct) {
            $productTagsType = array(
                "tags" => "",
                "type" => ""
            );

            if(!empty($magentoProduct)) {
                $productName = trim($magentoProduct['name']);
                $isConfigurable = trim($magentoProduct['type']) == "configurable" ? TRUE : FALSE;
                $productImage = trim($magentoProduct['image']);
                $productTaxable = strtolower(trim($magentoProduct['tax_class_id'])) == "taxable goods" ? TRUE : FALSE;
                $productPrice = trim($magentoProduct['price']);
                $productSpecialPrice = trim($magentoProduct['special_price']);
                $productStatus = trim($magentoProduct['status']);

                $colorOption = trim($magentoProduct['color']);
                $sizeOption = trim($magentoProduct['size']);

                if(!empty($productImage)) {
                    $productImage = "https://bobmarriottsflyfishingstore.com/media/catalog/product" . $productImage;
                }

                $productDetails = array(
                    "root" => FALSE,
                    "isSale" => FALSE,
                    "images" => [],
                    "variants" => []
                );

                if(isset($this->shopifyProducts[$productName])) {
                    $productDetails = $this->shopifyProducts[$productName];
                }

                
                $skuInProductDetails = $this->checkIfSkuExists($productDetails, $magentoProduct["sku"]);

                /* Multiple Images */
                if(empty($productStatus)) {
                    if($skuInProductDetails["isRoot"] != FALSE || $skuInProductDetails["variantIndex"] != FALSE) {
                        if($skuInProductDetails["isRoot"]) {
                            if(!empty($productImage)) {
                                $productDetails["images"][] = $productImage;
                            }
                        } elseif($skuInProductDetails["variantIndex"]) {
                            $variantImages = explode(";", $productDetails["variants"][$skuInProductDetails["variantIndex"]]["Variant Image"]);
    
                            if(!empty($productImage)) {
                                $variantImages[] = $productImage;
                            }
    
                            $productDetails["variants"][$skuInProductDetails["variantIndex"]]["Variant Image"] = implode(";", $variantImages);
                        }
    
                        $this->shopifyProducts[$productName] = $productDetails;
                    }

                    continue;
                } 

                if(!empty(trim($magentoProduct["category_ids"]))) {
                    $productTagsType = $this->getTagsType(explode(",", trim($magentoProduct["category_ids"])));
                }

                $shopifyProduct = [];

                $shopifyProduct["ID"] = "";
                $shopifyProduct["Handle"] = $magentoProduct['url_key'];
                $shopifyProduct["Command"] = "MERGE";
                $shopifyProduct["Title"] = $productName;
                $shopifyProduct["Body HTML"] = $this->removeInlineStyles($magentoProduct['description']);
                $shopifyProduct["Vendor"] = $magentoProduct["manufacturer"];
                $shopifyProduct["Type"] = $productTagsType["type"];
                $shopifyProduct["Tags"] = $productTagsType["tags"];
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
                $shopifyProduct["Top Row"] = "TRUE";
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
                $shopifyProduct["Variant Image"] = $productImage;
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
                $shopifyProduct["Image Src"] = "";
                $shopifyProduct["Image Command"] = "";
                $shopifyProduct["Image Position"] = "";
                $shopifyProduct["Image Width"] = "";
                $shopifyProduct["Image Height"] = "";
                $shopifyProduct["Image Alt Text"] = "";
                $shopifyProduct["Metafield: title_tag [string]"] = $magentoProduct['meta_title'];
                $shopifyProduct["Metafield: wholesale_price [string]"] = $magentoProduct['cost'];
                $shopifyProduct["Metafield: short_description [string]"] = $magentoProduct['short_description'];
                $shopifyProduct["Metafield: tax_class_id [string]"] = $magentoProduct['tax_class_id'];
                $shopifyProduct["Metafield: keyword [string]"] = $magentoProduct['meta_keyword'];

                if(!$productDetails["isSale"] && !empty($productSpecialPrice)) {
                    $productDetails["isSale"] = TRUE;
                }

                if($isConfigurable) {
                    $productDetails["root"] = $shopifyProduct;

                    if(!empty($productImage)) {
                        $productDetails["images"][] = $productImage;
                    }
                } else {
                    $productDetails["variants"][] = $shopifyProduct;
                }

                $this->shopifyProducts[$productName] = $productDetails;
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

        foreach ($this->shopifyProducts as $productIndex => $productDetails) {
            if($limit !== FALSE && $productIndex >= $limit) {
                break;
            }

            $hasRootProduct = ($productDetails["root"] != FALSE);

            if($hasRootProduct) {
                $productDetails["root"]["Top Row"] = "TRUE";
                if($productDetails["isSale"]) {
                    if(empty($productDetails["root"]["Tags"])) {
                        $productDetails["root"]["Tags"] = "Onsale";
                    } else {
                        $productDetails["root"]["Tags"] .= ",Onsale";
                    }
                }

                $productDetails["root"]["Image Src"] .= implode(";", $productDetails["images"]);

                $columnIndex = 1;
                foreach($productDetails["root"] as $item) {
                    $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $item);
                    $columnIndex++;
                }
                $rowIndex++;

                foreach ($productDetails["variants"] as $variant) {
                    $variant["Handle"] = $productDetails["root"]["Handle"];
                    $variant["Tags"] = $productDetails["root"]["Tags"];
                    $variant["Type"] = $productDetails["root"]["Type"];
                    $variant["Top Row"] = "";

                    $columnIndex = 1;

                    foreach ($variant as $product_key => $item) {
                        $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $item);
                        $columnIndex++;
                    }

                    $rowIndex++;
                }
            } else {
                foreach ($productDetails["variants"] as $variantIndex => $variant) {
                    if($productDetails["isSale"]) {
                        if(empty($variant["Tags"])) {
                            $variant["Tags"] = "Onsale";
                        } else {
                            $variant["Tags"] .= ",Onsale";
                        }
                    }

                    if($variantIndex == 0) {
                        $variant["Top Row"] = "TRUE";
                    } else {
                        $variant["Top Row"] = "";
                    }

                    $columnIndex = 1;

                    foreach ($variant as $product_key => $item) {
                        $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $item);
                        $columnIndex++;
                    }

                    $rowIndex++;
                }
            }

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
                <?php foreach ($productImporter->shopifyHeaders as $head): ?>
                    <th scope="col"><?php echo $head; ?></th>
                <?php endforeach; ?>
            </thead>
            <tbody>
                <?php foreach ($productImporter->shopifyProducts as $productIndex => $productDetails): ?>
                    <?php
                        if($productIndex >= 500) {
                            break;
                        }
                        $hasRootProduct = ($productDetails["root"] != FALSE);
                    ?>

                    <?php if($hasRootProduct): ?>
                        <?php
                            $productDetails["root"]["Top Row"] = "TRUE";
                            if($productDetails["isSale"]) {
                                if(empty($productDetails["root"]["Tags"])) {
                                    $productDetails["root"]["Tags"] = "Onsale";
                                } else {
                                    $productDetails["root"]["Tags"] .= ",Onsale";
                                }
                            }
                            $productDetails["root"]["Image Src"] .= implode(";", $productDetails["images"]);
                        ?>
                        <tr>
                            <?php foreach ($productDetails["root"] as $product_key => $item): ?>
                                <?php if ($product_key == "Body HTML"): ?>
                                    <td><code><?php echo substr(strip_tags($item),0, 100) . "..."; ?></code></td>
                                <?php else: ?>
                                    <td><?php echo $item; ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>

                        <?php foreach ($productDetails["variants"] as $variant): ?>
                            <?php
                                $variant["Handle"] = $productDetails["root"]["Handle"];
                                $variant["Tags"] = $productDetails["root"]["Tags"];
                                $variant["Type"] = $productDetails["root"]["Type"];
                                $variant["Top Row"] = "";
                            ?>
                            <tr>
                                <?php foreach ($variant as $product_key => $item): ?>
                                    <?php if ($product_key == "Body HTML"): ?>
                                        <td><code><?php echo substr(strip_tags($item),0, 100) . "..."; ?></code></td>
                                    <?php else: ?>
                                        <td><?php echo $item; ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($productDetails["variants"] as $variant): ?>
                            <tr>
                                <?php foreach ($variant as $product_key => $item): ?>
                                    <?php if ($product_key == "Body HTML"): ?>
                                        <td><code><?php echo substr(strip_tags($item),0, 100) . "..."; ?></code></td>
                                    <?php else: ?>
                                        <td><?php echo $item; ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </div>
    
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>
</html>