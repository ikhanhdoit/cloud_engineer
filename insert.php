<?php
$servername = "cloudengineerdb2.csy5s34kiuf7.us-east-1.rds.amazonaws.com";
$username = "www";
$password = "www";
$dbname = "fortune";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

$Fortune = $_POST['fortune'];

$sql = "INSERT INTO fortunes (id, fortune) VALUES (null,'$Fortune')";

if (mysqli_query($conn, $sql)) {
        echo 'Inserted';
}
else {
        echo 'Not Inserted';
}


header("refresh:2; url=index.php");

$conn->close();
?>
