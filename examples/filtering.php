<?php

use Bakame\Csv\Reader;

require '../vendor/autoload.php';

 //you can instantiate the Reader class with a SplFileObject object
$inputCsv = new Reader(new SplFileObject('data/prenoms.csv'));
$inputCsv->setDelimiter(';');
$inputCsv->setEncoding("iso-8859-15");

//we order the result according to the number of firstname given
$sortBy = function ($row1, $row2) {
    return strcmp($row1[1], $row2[1]);
};

$res = $inputCsv
    ->setFilter(function ($row, $index) {
        return $index > 0; //we don't take into account the header
    })
    ->setFilter(function ($row, $index) {
        return isset($row[2]) && 'F' == $row[2]; //we are only interested in girl firstname
    })
    ->setFilter(function ($row, $index) {
        return isset($row[3]) && 2010 == $row[3]; //we are looking for the year 2010
    })
    ->setFilter(function ($row, $index) {
        return isset($row[1]) && 10 > $row[1]; //the name is used less than 10 times
    })
    ->setSortBy(1, SORT_ASC)
    ->setLimit(20) //we just want the first 20 results
    ->fetchAll();

//get the headers
$headers = $inputCsv->fetchOne(0);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="<?=$inputCsv->getEncoding()?>">
    <title>\Bakame\Csv\Reader filtering method</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>
<h1>Using the Bakame\Csv\Reader class filtering capabilities</h1>
<table class="table-csv-data">
<caption>Statistics for the 20 least used female name in the year 2010</caption>
<thead>
    <tr>
        <th><?=implode('</th>'.PHP_EOL.'<th>', $headers), '</th>', PHP_EOL; ?>
    </tr>
</thead>
<tbody>
<?php foreach ($res as $row) : ?>
    <tr>
    <td><?=implode('</td>'.PHP_EOL.'<td>', $row), '</td>', PHP_EOL; ?>
    </tr>
    <?php
endforeach;
?>
</tbody>
</table>
</body>
</html>