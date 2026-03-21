<?php
session_start();


$userName = $_POST['username'];
$password = $_POST['password'];
$email = $_POST['email'];
$color = $_POST['color'];

$passwordHashed = password_hash( $password, PASSWORD_DEFAULT );
$conn = mysqli_connect( "localhost", "zack", "BradburyLeilani1", "myapp" );

if ( mysqli_connect_errno() ) {
    echo "Failed to connect to database: <br>".mysqli_connect_error();
} else {
    echo "Connected to database successfully <br>";
}
$query = "SELECT * FROM `users` WHERE `username` = '".$userName."' LIMIT 1";
$result = mysqli_query( $conn, $query );
$row = mysqli_fetch_array( $result );

if ( $row ) {
    echo " Already a user called: ".$userName."<br>";
} else {
    echo "hello ".$userName."<br>";
    $query = "INSERT INTO `users` (`id`, `username`, `password`,`color`, `email`) VALUES (NULL, '".$userName."', '".$passwordHashed."','$color', '".$email."');";
    mysqli_query( $conn, $query );
    echo "<br>".$query;
    echo "<br>$userName has been created!";
    header("Location: loginpage.php");
    exit();

    
    $query = "SELECT * FROM `users` WHERE `username` = '".$userName."' LIMIT 1";
    echo $query;
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_array($result);

    if($row) {
        if (password_verify($password, $row['password'])) {
            // password is correct, return the user
            echo $userName." Now Signed in!";
            $_SESSION["username"] = "$userName";
            $_SESSION["bio"] = "$bio";
            $_SESSION["color"] = $row['color'];
            $_SESSION["loggedin"] = "yes";
            header("Location: loginPage.php");
            exit();
        
        } else {
            // incorrect password
            echo $userName." failed to sign in.";
        }
        }
    else{
        echo $userName." doesnt exist!";
    }
    echo "<br><a href=\"loginPage.php\">Back</a>";

}
?>
