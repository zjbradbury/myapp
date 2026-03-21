<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userName = $_POST['username'];
$password = $_POST['password'];

echo  "hello ".$userName;
//$passwordHashed = password_hash($password, PASSWORD_DEFAULT);

$conn = mysqli_connect("mariadb", "zack", "BradburyLeilani1", "myapp");

if(mysqli_connect_errno()) {
  echo "Failed to connect to database: <br>".mysqli_connect_error();
} else {
}

$query = "SELECT * FROM `users` WHERE `username` = '".$userName."' LIMIT 1";
// echo $query;
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_array($result);

if($row) {
  echo "<br> $userName exists<br>";
    if (password_verify($password, $row['password'])) {
        // password is correct, return the user
        echo $userName." Now Signed in!";
        $_SESSION["username"] = "$userName";
        $_SESSION["bio"] = " ";
        $_SESSION["color"] = $row['color'];
        $_SESSION["loggedin"] = "yes";
        header("Location: index.php");
        exit();
    
    } else {
        // incorrect password
        echo $userName." failed to sign in.";
    }
    }
else{
    echo "<br>" . $userName." doesnt exist!";
}
echo "<br><a href=\"loginPage.php\">Back</a>";
?>
