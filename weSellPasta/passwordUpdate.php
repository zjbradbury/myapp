<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<html>

<head>
    <title>Update Added</title>
    <link rel="stylesheet" href="style.css">
        <meta name="viewport" content="width=device-width, initial-scale=1">

<body>

<?php
$userName = $_SESSION["username"];

$password = $_POST['password'];
$passwordHashed = password_hash( $password, PASSWORD_DEFAULT );


$conn = mysqli_connect("localhost", "root", "", "bookface");
if(mysqli_connect_errno()) {
  echo "Failed to connect to database: <br>".mysqli_connect_error();
} else {
}

$query = "UPDATE `users` SET `password` = '".$passwordHashed."' WHERE`username` = '".$userName."' ";



//  echo $password;


$result = mysqli_query($conn, $query);

// echo $query; 

?>

<script>

   location.replace("logoutPage.php");

</script>

</body>
</head>

</html>

