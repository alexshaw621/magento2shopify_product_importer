<?php

$servername = "localhost";
$username = "root";
$password = "";
$database = "simple101";
$dbPrefix = "bobm";
$siteURL = "http://test.com";
$fileLocation = "D:/Development/_SimpleSolutions/BobMarriott/Products.csv";

//Create Connection
$conn = new mysqli($servername, $username, $password, $database);

//Check Connection
if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully<br/>";

try
{
	$handle = fopen($fileLocation, 'w');
	$heading = array('ID,', 'Handle,', 'Command,', 'Title,', 'Body HTML,', 'Vendor,', 'Type,', 'Tags,', 'Tags Command,', 'Created At,', 'Updated At,', 'Status,', 'Published,', 'Published At,', 'Published Scope,', 'Template Suffix,', 'Gift Card,', 'Row #,', 'Top Row,', 'Custom Collections,', 'Image Src,', 'Image Command,', 'Image Position,', 'Image Width,', 'Image Height,', 'Image Alt Text,', 'Variant ID,', 'Variant Command,', 'Option1 Name,', 'Option1 Value,', 'Option2 Name,', 'Option2 Value,', 'Option3 Name,', 'Option3 Value,', 'Variant Generate From Options,', 'Variant Position,', 'Variant SKU,', 'Variant Weight,', 'Variant Weight Unit,', 'Variant HS Code,', 'Variant Country of Origin,', 'Variant Price,', 'Variant Compare At Price,', 'Variant Cost,', 'Variant Requires Shipping,', 'Variant Taxable,', 'Variant Tax Code,', 'Variant Barcode,', 'Variant Image,', 'Variant Inventory Tracker,', 'Variant Inventory Policy,', 'Variant Fulfillment Service,', 'Variant Inventory Qty,','Variant Inventory Adjust,', 'Metafield: description_tag,', 'Metafield: title_tag,', 'Mtafield: specs.range[integer],', 'Variant Metafield: something[string],', 'Metafield: custom.json[json_string]');
	$feed_line = implode("\t", $heading)."\r\n";
	//$feed_line = '"ID""Handle""Command""Title""Body HTML""Vendor""Type""Tags""Tags Command""Created At""Updated At""Status""Published""Published At""Published Scope""Template Suffix""Gift Card""Row #""Top Row""Custom Collections""Image Src""Image Command""Image Position""Image Width""Image Height""Image Alt Text""Variant ID""Variant Command""Option1 Name""Option1 Value""Option2 Name""Option2 Value""Option3 Name""Option3 Value""Variant Generate From Options""Variant Position""Variant SKU""Variant Weight""Variant Weight Unit""Variant HS Code""Variant Country of Origin""Variant Price""Variant Compare At Price""Variant Cost""Variant Requires Shipping""Variant Taxable""Variant Tax Code""Variant Barcode""Variant Image""Variant Inventory Tracker""Variant Inventory Policy""Variant Fulfillment Service""Variant Inventory Qty""Variant Inventory Adjust""Metafield: description_tag""Metafield: title_tag""Metafield: specs.range[integer]""Variant Metafield: something[string]""Metafield: custom.json[json_string]"';
	fwrite($handle, $feed_line);


	$query = "SELECT * FROM " . $dbPrefix . "catalog_product_flat_1 WHERE visibility = 4";
	//$query = "SELECT * FROM ".$dbPrefix."catalog_product_flat_1 WHERE visibility = 4 AND entity_id < 500";
	$products = mysqli_query($conn, $query);

	foreach($products as $product) {
		$product_data = array();
		//$product_data['id'] = $product['entity_id'];
		$product_data['id'] = "";
		$product_data['handle'] = $product['url_key'];
		$product_data['command'] = "MERGE";
		$product_data['title'] = ucfirst(strtolower($product['name']));
		$product_data['body_html'] = $product['short_description'];
		$product_data['vendor'] = $product['manufacturer_value'];
		$product_data['type'] = $product['type_id'];
		$product_data['tags'] = "???TAGS???";
		$product_data['tags_command'] = "REPLACE";
		$product_data['created_at'] = $product['created_at'];
		$product_data['updated_at'] = $product['updated_at'];
		$product_data['status'] = $product['status'];
		$product_data['published'] = "???PUBLISHED???";
		$product_data['published_at'] = "???PUBLISHED AT???";
		$product_data['published_scope'] = "global";
		$product_data['template_suffix'] = "";
		$product_data['gift_card'] = "???GIFT CARD???";
		$product_data['row_num'] = "???ROW #???";
		$product_data['top_row'] = "???TOP ROW???";

		//20th column
		//Get Categories
		$product_data['product_type'] = "";
		$i = 1;

		$queryCats = "SELECT name FROM ".$dbPrefix."catalog_category_product JOIN ".$dbPrefix."catalog_category_flat_store_1 ON ".$dbPrefix."catalog_category_product.category_id = ".$dbPrefix."catalog_category_flat_store_1.entity_id WHERE product_id = {$product['entity_id']}";
 
		$cats = mysqli_query($conn, $queryCats);
		//$numCat = count($cats);
		$numCat = 0;
		foreach($cats as $cat){
			$numCat = $numCat + 1;
		}
		foreach($cats as $_category){
			if ($i == $numCat)
				$product_data['product_type'] .= $_category['name'];
			else
				$product_data['product_type'] .= $_category['name'] ." > ";
			$i++;
		}

		//$product_data['custom_collections'] = $product_data['product_type'];
		$product_data['image_src'] = $siteURL . $product['small_image'];
		$product_data['image_command'] = "MERGE";
		$product_data['image_position'] = "???IMAGE POSITION???";
		$product_data['image_width'] = "";
		$product_data['image_height'] = "";
		$product_data['image_alt_text'] = "Product Image: ".$product['name'];
		$product_data['variant_id'] = "";
		$product_data['variant_command'] = "MERGE";
		$product_data['option1_name'] = "???OPTION1 NAME???";
		$product_data['option1_value'] = "???OPTION1 VALUE???";
		$product_data['option2_name'] = "???OPTION2 NAME???";
		$product_data['option2_value'] = "???OPTION2 VALUE???";
		$product_data['option3_name'] = "???OPTION3 NAME???";
		$product_data['option3_value'] = "???OPTION3 VALUE???";
		$product_data['variant_generate_from_options'] = "???FALSE???";
		$product_data['variant_position'] = "???VARIANT POSITION???";
		$product_data['variant_sku'] = $product['sku'];
		$product_data['variant_weight'] = $product['weight'];
		$product_data['variant_weight_unit'] = $product['weight_type'];
		$product_data['variant_hs_code'] = "???VARIANT HS CODE???";
		$product_data['variant_country_of_origin'] = "???VARIANT COUNTRY OF ORIGIN???";
		$product_data['variant_price'] = $product['price'];
		$product_data['variant_compare_at_price'] = $product['msrp'];
		$product_data['variant_cost'] = $product['cost'];
		$product_data['variant_requires_shipping'] = "???VARIANT REQUIRES SHIPPING???";
		$product_data['variant_taxable'] = "???VARIANT TAXABLE???";
		$product_data['variant_tax_code'] = "";
		$product_data['variant_barcode'] = "???VARIANT BARCODE???";
		$product_data['variant_image'] = $siteURL . $product['small_image'];
		$product_data['variant_inventory_tracker'] = "shopify";
		$product_data['variant_inventory_policy'] = "deny";
		$product_data['variant_fulfillment_service'] = "???VARIANT FULFILLMENT SERVICE???";
		$product_data['variant_inventory_qty'] = "???VARIANT INVENTORY QTY???";
		$product_data['variant_inventory_adjust'] = "";
		$product_data['metafield_description_tag'] = $product['short_description'];
		$product_data['metafield_title_tag'] = $product['name'];
		$product_data['metafield_specs_range_integer'] = "???METAFIELD: SPECS.RANGE[INTEGER]???";
		$product_data['variant_metafield_something_string'] = "???VARIANT METAFIELD: SOMETHING[STRING]???";
		$product_data['metafield_custom_json_json_string'] = "???METAFIELD: CUSTOM.JSON[JSON_STRING]???";
		

		//$product_data['sku'] = $product['sku'];
		//$product_data['mpn'] = $product['sku'];
		//$product_data['ean'] = $product['sku'];
		//$product_data['google_product_category'] = 'INSERT YOUR DEFAULT CATEGORY';

		foreach($product_data as $k=>$val) {
			$bad = array('"',"\r\n","\n","\r","\t");
			$good = array(""," "," "," ","");
			$product_data[$k] = '"'.str_replace($bad, $good, $val).'"';
		}
		 
		$feed_line = implode("\t", $product_data)."\r\n";
		//fwrite($handle, $feed_line);
		fputcsv($handle, $product_data);
		fflush($handle);
	}

	fclose($handle);
	echo "File created successfully and can be found here: ".$fileLocation;
}
catch(Exception $e)
{
	die($e->getMessage());
}

$conn->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Export Magento to CSV</title>
</head>
<body>
</body>
</html>