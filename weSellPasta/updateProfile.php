<html>

<head>
    <title>Update Added</title>
    <link rel="stylesheet" href="style.css">
        <meta name="viewport" content="width=device-width, initial-scale=1">

<body>

<?php
session_start();
$userName = $_SESSION["username"];
$bio = $_SESSION["bio"];
$color = $_SESSION["color"];


$conn = mysqli_connect("localhost", "root", "", "bookface");
if(mysqli_connect_errno()) {
  echo "Failed to connect to database: <br>".mysqli_connect_error();
} else {
}

$query2="SELECT * FROM `users` WHERE `username` = '".$userName."' LIMIT 1";
$result2=mysqli_query($conn, $query2);
while($row=mysqli_fetch_array($result2)) {
'<script>
var files = fileToUpload.files;
</script>';

if ('files.length' == 0) {

$target_dir = "images/";
  $target_file = $target_dir. basename($_FILES["fileToUpload"]["name"]);
  
  if(move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)){
      // echo "<script type='text/javascript'>
      //             location.replace('addedToDatabase.php');
      //         </script>";
  }


else{
  $target_file = $row["pic"];
}
}
}

$query = "UPDATE `users` SET `username`='".$_POST['username']."', pic = '$target_file', `bio`='".$_POST['bio']."', `color`='".$_POST['color']."' WHERE`username` = '".$userName."' ";

$userName = $_POST['username'];
$bio = $_POST['bio'];
$color = $_POST['color'];


// echo $userName . $bio . $color;


$result = mysqli_query($conn, $query);

// echo $query; 

?>

<script>

   location.replace("editProfilePage.php");

</script>

</body>
</head>

</html>
