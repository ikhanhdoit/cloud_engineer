<?php
$servername = "[RDS endpoint]";
$username = "[RDS username]";
$password = "[RDS password]";
$dbname = "[MySQL database name]";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
}

$mysql = "SELECT [row] FROM [db table name] ORDER BY rand() LIMIT 1";

echo '<span style ="font-size: 20px;">Here is your Fortune of the Day:';
echo '<br/>';
echo '<br/>';


foreach ($conn->query($mysql) as $row) {
        print $row['[MySQL database name]'];
}

$conn->close();
?>
