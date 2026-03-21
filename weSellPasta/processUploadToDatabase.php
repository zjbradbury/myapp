    <?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<html>

<head>
    <title>Update Added</title>
    <link rel="stylesheet" href="../style.css">
        <meta name="viewport" content="width=device-width, initial-scale=1">

<body>

    <?php
    $userName = $_SESSION["username"];

    if(isset($_FILES['fileToUpload'])){

$target_dir = "images/";
$target_file = $target_dir. basename($_FILES["fileToUpload"]["name"]);

if(move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)){
    echo "<script type='text/javascript'>
                location.replace('addedToDatabase.php');
            </script>";

            $conn = mysqli_connect("localhost", "root", "", "bookface");
if(mysqli_connect_errno()) {
  echo "Failed to connect to database: <br>".mysqli_connect_error();
} else {
}
    
$query = "INSERT INTO `posts`(`id`, `username`, `title`, `content`, `pic`, `time`) VALUES (NULL, '$userName', '".$_POST['title']."', '".$_POST['content']."', '$target_file', current_timestamp());";

    $result = mysqli_query($conn, $query);

}
else{
    echo "<script type='text/javascript'>
    location.replace('postUpload.php');
</script>";
}
}


    ?>
</body>
</head>

</html>

