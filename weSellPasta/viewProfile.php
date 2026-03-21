<?php include 'nav.php';

// session_start();
if ($_SESSION["loggedin"]=="yes") {

    $userName = $_SESSION["username"]
    ?>

<html>

<head>
    <title>Your Profile Page </title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body><br>
    <center>
        <?php 
        $conn=mysqli_connect("mariadb", "zack", "BradburyLeilani1", "users");

    if (mysqli_connect_errno()) {
        echo "Failed to connect to database: <br>".mysqli_connect_error();
    }

    else {}

    $query="SELECT * FROM `users` WHERE `username` = '".$userName."'";
    $result=mysqli_query($conn, $query);
    // $row = mysqli_fetch_array( $result );

    while($row=mysqli_fetch_array($result)) {

        echo '<div id="main-holder"><br>';
        echo '<h1>'. $userName .' Profile Page</h1>';
        
        // echo '<img width="10%" src="' . $row['pic'] . '">';

        echo '<h1>' . $row['username'] . '</h1>';

        // echo $row['bio'] . '<br>';
        
        echo '<br><br></div>';
        
    }
    echo '<br><br>';

    $query2="SELECT * FROM `posts` WHERE `username` = '".$userName."' ORDER BY `time` DESC";
    $result2=mysqli_query($conn, $query2);

    while($row=mysqli_fetch_array($result2)) {

        echo '<div id="main-holder"><br>';

        echo $row['time'] . '<br>';

        echo '<img width="40%" src="' . $row['pic'] . '">';

        echo '<h1>' . $row['title'] . '</h1>';

        echo $row['content'] . '<br>';
        
        echo '<br><br></div>';
        echo '<br><br>';
    }

    ?>



    </center>
    <br><br>
</body>

</html>
<?php
}

else {
    echo "<script type='text/javascript'>
location.replace('loginPage.php');
    </script>";

}

?>