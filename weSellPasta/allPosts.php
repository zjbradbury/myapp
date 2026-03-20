<?php include 'nav.php';

// session_start();
if ($_SESSION["loggedin"]=="yes") {
    
    ?>

<html>

<head>
    <title>All Posts</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body><br>
    <center>

            <?php $conn=mysqli_connect("localhost", "root", "", "bookface");


    if (mysqli_connect_errno()) {
        echo "Failed to connect to database: <br>".mysqli_connect_error();
    }

    else {}

    $query2="SELECT * FROM `posts` ORDER BY `time` DESC";
    $result2=mysqli_query($conn, $query2);

    while($row=mysqli_fetch_array($result2)) {

        echo '<div id="main-holder"><br>';

        echo '<h1>Post By ' .$row['username'] . '</h1>';

        echo $row['time'] . '<br>';

        echo '<img width="40%" src="' . $row['pic'] . '">';

        echo '<h1>' . $row['title'] . '</h1>';

        echo $row['content'] . '<br>';
        
        echo '<br><br></div>';
        echo '<br><br>';

    }


    ?>

            <br>
    </center>
</body>


<?php
}

else {
    echo "<script type='text/javascript'>
location.replace('loginPage.php');
    </script>";

}

?>
</html>