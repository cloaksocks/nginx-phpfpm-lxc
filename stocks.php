<?php
echo '<h4>financial_exchange stocks </h4>';
$host = '10.243.60.168';
$port = '5433';
$dbname = 'financial_exchange';
$user = 'stocks_viewer';
$password = 'password';

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
  if (!$conn){
  echo "connection error \n";
}

$result = pg_query($conn, "SELECT * FROM stocks");
if (!$result) {
  echo "An error occurred.\n";
  exit;
}

while ($row = pg_fetch_row($result)) {
  echo "stock_id: $row[0]  company_id: $row[1]  price: $row[2]  quantity: $row[3]  timestamp: $row[4]";
  echo "<br />\n";
}

?>
