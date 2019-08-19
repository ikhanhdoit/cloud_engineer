<?php
$servername = "[db Container IP:port]";
$username = "root";
$password = "www";
$dbname = "fortune";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT fortune FROM fortunes ORDER BY rand() LIMIT 1";

foreach ($conn->query($sql) as $row) {
        print $row['fortune'];
}


$conn->close();
?>
