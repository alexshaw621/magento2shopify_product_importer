<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet as Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Xlsx;

class CollectionImporter
{
    public $shopifyHeaders = [];
    public $shopifyCollections = [];
    public $magenotCollectionsById = [];
	public $magenotCollectionsByUrl = [];

    function __construct()
	{
        $this->shopifyHeaders = [
            "Title",
            "Command",
            "Body HTML",
            "Sort Order",
            "Template Suffix",
            "Published",
            "Published Scope",
            "Image Src",
            "Image Width",
            "Image Height",
            "Image Alt Text",
            "Must Match",
            "Rule: Product Column",
            "Rule: Relation",
            "Rule: Condition"
        ];

        $this->getMagentoCollections();
        $this->generateShopifyCollections();
    }

    private function getMagentoCollections()
    {
		$row = 0;
        $this->magenotCollectionsById = [];
        $this->magenotCollectionsByUrl = [];

        if (($handle = fopen("csv_files/categories.csv", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 5000, ",")) !== FALSE) {
                if($row == 0) {
                    $this->collectionDataHeader = $data;
                } else {
                    $collection = [];
                    foreach($data as $key => $value) {
                        $collection[$this->collectionDataHeader[$key]] = $value;
                    }

                    $this->magenotCollectionsById[$collection["id"]] = $collection["url"];
                    $this->magenotCollectionsByUrl[$collection["url"]] = $collection["name"];
                }
                $row++;
            }
            fclose($handle);
        }
    }

    /* Generate shopify customers array from magento customers */
    public function generateShopifyCollections()
    {
        $this->shopifyCollections = [];

		$tagPrefixes = array(
            "landing:",
            "category:",
            "subcategory:",
            "group:",
            "material_type:"
        );

        foreach($this->magenotCollectionsById as $magentoCollection) {
            if(!empty($magentoCollection)) {
				if($magentoCollection == "root-catalog" || $magentoCollection == "/default-category") {
					continue;
				}

                $shopifyCollection = [];

				$breadcrumbs = explode("/", $magentoCollection);
				if(count($breadcrumbs) >= 2) {
					$collectionTitle = "";
					$collectionTags = [];

					foreach($breadcrumbs as $key => $breadcrumb) {
						$subBreadcrumbs = array_slice($breadcrumbs, 0, ($key + 1));
						$subUrl = implode("/", $subBreadcrumbs);

						if($key == 0) {
							$collectionTitle = $this->magenotCollectionsByUrl[$subUrl];
							$collectionTags[] = $tagPrefixes[$key] . $this->magenotCollectionsByUrl[$subUrl];
							continue;
						} else if ($key == 1) {
							$collectionTitle .= "###" . $this->magenotCollectionsByUrl[$subUrl];
						} else if ($key == 2) {
							$collectionTitle .= "@@@" . $this->magenotCollectionsByUrl[$subUrl];
						} else {
							continue;
						}

						if(!empty($collectionTitle) && $key > 0) {
							if(!isset($this->shopifyCollections[$collectionTitle])) {
								$this->shopifyCollections[$collectionTitle] = [];
							}

							$collectionTags[] = $tagPrefixes[$key] . $this->magenotCollectionsByUrl[$subUrl];

							$this->shopifyCollections[$collectionTitle] = array_unique(array_merge($this->shopifyCollections[$collectionTitle], $collectionTags));
						}
					}
				}
            }
        }
    }

    /* Generate excel file for products import using Matrixify */
    public function exportShopifyCollections()
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

        foreach ($this->shopifyCollections as $title => $tags) {
			foreach($tags as $tag) {
				$collectionRow = [];

				$collectionRow["Title"] = $title;
				$collectionRow["Command"] = "MERGE";
				$collectionRow["Body HTML"] = "";
				$collectionRow["Sort Order"] = "Best Selling";
				$collectionRow["Template Suffix"] = "";
				$collectionRow["Published"] = "";
				$collectionRow["Published Scope"] = "global";
				$collectionRow["Image Src"] = "";
				$collectionRow["Image Width"] = "";
				$collectionRow["Image Height"] = "";
				$collectionRow["Image Alt Text"] = "";
				$collectionRow["Must Match"] = "all conditions";
				$collectionRow["Rule: Product Column"] = "Tag";
				$collectionRow["Rule: Relation"] = "Equals";
				$collectionRow["Rule: Condition"] = $tag;

				foreach($this->shopifyHeaders as $columnIndex => $header) {
					$sheet->setCellValueByColumnAndRow(($columnIndex + 1), $rowIndex, $collectionRow[$header]);
				}
	
				$rowIndex++;
			}
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save('exports/shopify_collections_' . time() . '.xlsx');
    }
}

$collectionImporter = new CollectionImporter();
$collectionImporter->exportShopifyCollections();
exit;
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