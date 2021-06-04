<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet as Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Xlsx;

class CustomerImporter
{
    public $shopifyHeaders = [];
    public $shopifyCustomers = [];
    public $magentoCustomers = [];
    public $magentoProducts = [];

    function __construct()
	{
        $this->shopifyHeaders = [
            "Email",
            "Command",
            "First Name",
            "Last Name",
            "Phone",
            "Accepts Marketing",
            "Tags Command",
            "Note",
            "Verified Email",
            "Tags",
            "Tags Command",
            "Send Account Activation Email",
            "Send Welcome Email",
            "Password",
            "Address Command",
            "Address First Name",
            "Address Last Name",
            "Address Phone",
            "Address Company",
            "Address Line 1",
            "Address Line 2",
            "Address City",
            "Address Province",
            "Address Province Code",
            "Address Country",
            "Address Country Code",
            "Address Zip",
            "Address Is Default"
        ];

        $this->getMagentoCustomers();
        $this->generateShopifyCustomers();
    }

    private function getMagentoCustomers()
    {
      	libxml_use_internal_errors(TRUE);

		$this->magentoCustomers = [];

      	for($i = 0; $i <= 2; $i++) {
			$objXmlDocument = simplexml_load_file("xml_files/Customer00" . $i . ".xml");
			
			if ($objXmlDocument === FALSE) {
				break;
			}

			$customerNodes = $objXmlDocument->children()->children();

			foreach($customerNodes as $nodeName => $nodeValue) {
				$jsonData = json_encode($nodeValue);
				$customer = json_decode($jsonData, TRUE);
				$this->magentoCustomers[] = $customer;
			}
      	}
    }


    /* Generate shopify customers array from magento customers */
    public function generateShopifyCustomers()
    {
        $this->shopifyCustomers = [];

        foreach($this->magentoCustomers as $magentoCustomer) {
            if(!empty($magentoCustomer)) {
                $shopifyCustomer = [];

                $customerEmail = trim($magentoCustomer["@attributes"]["email_addr"]);

                $shopifyCustomer["Email"] = $customerEmail;
                $shopifyCustomer["Command"] = "MERGE";
                $shopifyCustomer["First Name"] = trim($magentoCustomer["@attributes"]["first_name"]);
                $shopifyCustomer["Last Name"] = trim($magentoCustomer["@attributes"]["last_name"]);
                $shopifyCustomer["Phone"] = "";
                $shopifyCustomer["Accepts Marketing"] = "TRUE";
                $shopifyCustomer["Note"] = trim($magentoCustomer["@attributes"]["notes"]);
                $shopifyCustomer["Verified Email"] = empty($customerEmail) ? "FALSE" : "TRUE";
                $shopifyCustomer["Tags"] = "";
                $shopifyCustomer["Tags Command"] = "REPLACE";
                $shopifyCustomer["Send Account Activation Email"] = "FALSE";
                $shopifyCustomer["Send Welcome Email"] = "FALSE";
                $shopifyCustomer["Password"] = "";
                $shopifyCustomer["Address Command"] = "MERGE";
                $shopifyCustomer["Address First Name"] = trim($magentoCustomer["@attributes"]["first_name"]);
                $shopifyCustomer["Address Last Name"] = trim($magentoCustomer["@attributes"]["last_name"]);
                $shopifyCustomer["Address Company"] = trim($magentoCustomer["@attributes"]["company_name"]);
                $shopifyCustomer["Address Country"] = "USA";
                $shopifyCustomer["Address Country Code"] = "US";
                $shopifyCustomer["Address Is Default"] = "TRUE";

                $shopifyCustomer["Address Phone"] = "";
                $shopifyCustomer["Address Zip"] = "";
                $shopifyCustomer["Address City"] = "";
                $shopifyCustomer["Address Province"] = "";
                $shopifyCustomer["Address Province Code"] = "";
                $shopifyCustomer["Address Line 1"] = "";
                $shopifyCustomer["Address Line 2"] = "";

                if(isset($magentoCustomer["CUST_ADDRESSS"]["CUST_ADDRESS"])) {
                    $address = $magentoCustomer["CUST_ADDRESSS"]["CUST_ADDRESS"];

                    $cityProvince = explode(", ", $address["@attributes"]["address3"]);

                    if(empty($shopifyCustomer["Address Phone"])) {
                        $shopifyCustomer["Address Phone"] = $shopifyCustomer["Phone"] = trim($address["@attributes"]["phone1"]);
                    }

                    if(empty($shopifyCustomer["Address Phone"])) {
                        $shopifyCustomer["Address Phone"] = $shopifyCustomer["Phone"] = trim($address["@attributes"]["phone2"]);
                    }

                    if(empty($shopifyCustomer["Address Zip"])) {
                        $shopifyCustomer["Address Zip"] = trim($address["@attributes"]["zip"]);
                    }

                    if(empty($shopifyCustomer["Address City"]) && !empty($cityProvince[0])) {
                        $shopifyCustomer["Address City"] = $cityProvince[0];
                    }

                    if(empty($shopifyCustomer["Address Province"]) && !empty($cityProvince[1])) {
                        $shopifyCustomer["Address Province"] = $cityProvince[1];
                        $shopifyCustomer["Address Province Code"] = $cityProvince[1];
                    }

                    if(empty($shopifyCustomer["Address Line 1"])) {
                        $shopifyCustomer["Address Line 1"] = trim($address["@attributes"]["address1"]);
                    }

                    if(empty($shopifyCustomer["Address Line 2"])) {
                        $shopifyCustomer["Address Line 2"] = trim($address["@attributes"]["address2"]);
                    }
                }

                $this->shopifyCustomers[] = $shopifyCustomer;
            }
        }
    }

    /* Generate excel file for products import using Matrixify */
    public function exportShopifyCustomers($limit = FALSE)
    {
        $columnIndex = 1;
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');

        foreach($this->shopifyHeaders as $columnIndex => $header) {
            $sheet->setCellValueByColumnAndRow(($columnIndex + 1), 1, $header);
            $columnIndex++;
        }

        $rowIndex = 2;

        foreach ($this->shopifyCustomers as $customerIndex => $customer) {
            if($limit !== FALSE && $customerIndex >= $limit) {
                break;
            }

            foreach($this->shopifyHeaders as $columnIndex => $header) {
                $sheet->setCellValueByColumnAndRow(($columnIndex + 1), $rowIndex, $customer[$header]);
            }

            $rowIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save('exports/shopify_customers_' . time() . '.xlsx');
    }
}

$customerImporter = new CustomerImporter();
$customerImporter->exportShopifyCustomers(500);
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
        <?php if (!empty($customerImporter->shopifyCustomers)): ?>

        <table class="table table-bordered">
            <thead>
                <?php foreach ($customerImporter->shopifyHeaders as $head): ?>
                    <th scope="col"><?php echo $head; ?></th>
                <?php endforeach; ?>
            </thead>
            <tbody>
                <?php foreach ($customerImporter->shopifyCustomers as $customerIndex => $customer): ?>
                    <?php
                        if($customerIndex >= 500) {
                            break;
                        }
                    ?>

                    <tr>
                        <?php foreach ($customerImporter->shopifyHeaders as $head): ?>
                            <td><code><?php echo $customer[$head]; ?></code></td>
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