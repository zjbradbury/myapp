<?php include 'nav.php';
    ?>

    <html>

<head>
    <title>Profile Page </title>
    <link rel="stylesheet" href="/weSellPasta/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body><br>
    <center>
        <div id="main-holder"><br>
        <?php 
        $conn=mysqli_connect("mariadb", "zack", "BradburyLeilani1", "myapp");

    if (mysqli_connect_errno()) {
        echo "Failed to connect to database: <br>".mysqli_connect_error();
    }

    else {}

    $query="SELECT * FROM `posts`";
    $result=mysqli_query($conn, $query);
    // $row = mysqli_fetch_array( $result );

    echo '<div class="grid-container">';

    while($row=mysqli_fetch_array($result)) {
        echo'<div class="grid-item">';

        echo '<img width="100vw" src="' . $row['pic'] . '""> <br>';
        echo '<h1>' .$row['username']. '</h1>';
        // echo $row['bio'] . '<br><br>';
        
        echo "<a href='otherUser.php?userSelect=".$row['username']."' id='viewProfile'>View Profile</a>" . '<br>';
        echo '</div>';
    }
    echo' </div>';

    ?>
    
    <br><br>
</div>
    </center>
    <br><br>
</body>

</html>